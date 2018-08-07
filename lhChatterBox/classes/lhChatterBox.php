<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of lhChatterBox
 *
 * @author user
 */
require_once __DIR__ . '/../abstract/lhAbstractChatterBox.php';

class lhChatterBox extends lhAbstractChatterBox {
    
    public function __construct($session_id) {
        $this->session = new lhSessionFile($session_id);
        $this->aiml = new lhAIML();
        $this->csml = new lhCSML();
    }
    
    public function process($text) {
        $this->text = $text;
        $this->session->log(lhSessionFile::$facility_chat, 'IN', $text);
        switch ($this->session->get('status', 'script')) {
            case 'script':
                $answer = $this->answerFromCsmlBlock($this->doScript());
                break;
            case 'babbler':
                $answer = $this->answerFromAimlCategory($this->doAiml());
                break;
            case 'proxy':
                
            default:
                $answer = false;
        }
        $this->session->log(lhSessionFile::$facility_chat, 'OUT', $answer['text']);
        return $answer;
    }
    
    public function scriptStart($block_name=null) {
        $this->session->set('status', 'script');
        $this->session->set('script_state', (string)$block_name);

        return $this->answerFromCsmlBlock($this->csml->block($block_name));
    }
    
    private function doScript() {
        $this->csml->start($this->session->get('script_state', 'start'));
        $answer = $this->csml->answer($this->text, $this->session->get('min_hit_ratio_csml', 50));

        // Получили подходящий ответ и работаем с ним
        $this->setVars($answer);
        if ((string)$answer->next != '') {
            $this->session->set('script_state', (string)$answer->next);
            $block = $this->csml->block((string)$answer->next);
            $this->setVars($block);
            return $block;
        }
        throw new Exception("Блок \"".$this->session->get('script_state', 'start')."\" не содержит ссылку на следующий блок."); 
    }
    
    private function doAiml() {
        $context = $this->session->get('context', '');
        $tags = $this->session->get('tags', '');
        $matches = $this->aiml->bestMatches($this->text, $tags, $this->session->get('min_hit_ratio_csml', 75));
        if ( $context ) {
            $matches = array_merge(
                $matches,
                $this->aiml->bestMatches($this->text.' '.$context, $tags, $this->session->get('min_hit_ratio_csml', 75)),    
                $this->aiml->bestMatches($context.' '.$this->text, $tags, $this->session->get('min_hit_ratio_csml', 75))    
            );
        }
        krsort($matches);
        if (count($matches)) {
            $category = array_shift($matches)[1];
        } else {
            $matches = $this->aiml->bestMatches('Любая фигня', '#anyway');
            $category = array_shift($matches)[1];
        }
        return $category;
    }
    
    private function answerFromCsmlBlock($block) {
        $hints = [];
        foreach ($block->hint as $hint) {
            $hints[] = (string)$hint;
        }
        $templates = [];
        foreach ($block->template as $template) {
            $templates[] = (string)$template;
        }
        $answer = [
            'text' => $this->subst($templates[rand(0, count($templates)-1)]),
            'hints' =>  $hints
        ];
        return $answer;
    }
    
    private function answerFromAimlCategory($category) {
        foreach ($category->template as $template) {
            $templates[] = $template;
        }
        $rand = rand(0, count($templates)-1);
        $selected = $templates[$rand];
        $this->setVars($selected);

        $answer = [
            'text' => $this->subst($selected->text)
        ];
        return $answer;
    }


    private function setVars($parent_object) {
        $this->session->set('context', '');
        $this->session->set('tags', '');
        foreach ($parent_object->var as $var) {
            $value = (string)$var;
            $this->session->set((string)$var['name'], $this->subst($value, $parent_object));
            switch ($var['name']) {
                case 'script_file':
                    $this->csml->loadCsml($value);
                    break;
                case 'aiml_file':
                    $this->aiml->loadAiml($value);
                    break;
            }
        }
    }
    
    private function subst($text, $obj=null) {
        if ($obj && $obj->validated) {
            $validated = json_decode((string)$obj->validated, true);
        } else {
            $validated = [];
        }
        
        $n = new lhRuNames();
        
        $result = $text;
        $result = preg_replace("/__user_answer__/", $this->text, $result);
        
        if (preg_match("/__vocative__/", $result)) {
            $vocative = $n->shortVocative($this->session->get('name', '## UNDEF ##'));
            $result = preg_replace("/__vocative__/", $vocative[rand(0, count($vocative)-1)], $result);
        }
        
        $result = preg_replace_callback("/__validated_(\w+)__/", function ($matches) use($validated) {
            return isset($validated[$matches[1]]) ? $validated[$matches[1]] : '## UNDEF ##';
        }, $result);
        
        $result = preg_replace_callback("/__(\w+)__/", function ($matches) {
            return $this->session->get($matches[1], '## UNDEF ##');
        }, $result);
 
        return $result;
    }
}
