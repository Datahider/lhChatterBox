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
                $answer = $this->doScript();
                break;
            case 'babbler':
                $answer = $this->doAiml();
                break;
            default:
                $answer = false;
        }
        $this->session->log(lhSessionFile::$facility_chat, 'OUT', $answer['text']);
        return $answer;
    }
    
    public function scriptStart($block_name=null) {
        $this->session->set('status', 'script');
        $this->session->set('script_state', (string)$block_name);

        $block = $this->csml->block($block_name);
        $this->setVars($block);
        $answer = $this->answerFromCsmlBlock($block);
        $this->session->log(lhSessionFile::$facility_chat, 'OUT', $answer['text']);
        return $answer;
    }
    
    private function doScript() {
        $this->csml->start($this->session->get('script_state', 'start'));
        $xml = $this->csml->getCsml();
        $min_hit_ratio = isset($xml['minhit']) ? $xml['minhit'] : 70;
        $answer = $this->csml->answer($this->text, $this->session->get('min_hit_ratio_csml', $min_hit_ratio));

        // Нужно проверить. Если нет ответа по умолчанию, а остальные не подошли - попробуем разрулить ситуацию при помощи aiml
        if ($answer === false) {
            $aiml_answer = $this->doAiml();
            if ($aiml_answer !== false) {
                return $aiml_answer;
            }
        }
        // Получили подходящий ответ и работаем с ним
        $this->setVars($answer);
        if ((string)$answer->next != '') {
            $this->session->set('script_state', (string)$answer->next);
            $block = $this->csml->block((string)$answer->next);
            $this->setVars($block);
            return $this->answerFromCsmlBlock($block);
        }
        throw new Exception("The block \"".$this->session->get('script_state', 'start')."\" does not contain a refference to next block."); 
    }
    
    private function doAiml($stupid=true) {
        $context = $this->session->get('context', '');
        $tags = $this->session->get('tags', '');
        $xml = $this->aiml->getAiml();
        $min_hit_ratio = isset($xml['minhit']) ? $xml['minhit'] : 80;
        $matches = $this->aiml->bestMatches($this->text, $tags, $this->session->get('min_hit_ratio_csml', $min_hit_ratio));
        if ( $context ) {
            $matches = array_merge(
                $matches,
                $this->aiml->bestMatches($this->text.' '.$context, $tags, $this->session->get('min_hit_ratio_csml', $min_hit_ratio)),    
                $this->aiml->bestMatches($context.' '.$this->text, $tags, $this->session->get('min_hit_ratio_csml', $min_hit_ratio))    
            );
        }
        krsort($matches);
        if (count($matches)) {
            $category = array_shift($matches)[1];
        } elseif ($stupid) { // Если работаем в режиме глупца - вернем ответ на все случаи жизни
            $matches = $this->aiml->bestMatches('Любая фигня', '#anyway');
            $category = array_shift($matches)[1];
        } else { // В режиме умного - промолчим - за умного сойдем
            return false;
        }
        return $this->answerFromAimlCategory($category);
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
        $this->setVars($category);
        foreach ($category->template as $template) {
            $templates[] = $template;
        }
        $rand = rand(0, count($templates)-1);
        $selected = $templates[$rand];
        $this->setVars($selected);
        $hints = [];
        foreach ($selected->hint as $hint) {
            $hints[] = (string)$hint;
        }
        
        $answer = [
            'text' => $this->subst($selected->text),
            'hints' => $hints
        ];
        return $answer;
    }


    private function setVars($parent_object) {
        $this->session->set('context', '');
        $this->session->set('tags', '');
        foreach ($parent_object->var as $var) {
            $value = (string)$var;
            if (($var['name'] == 'needhelp') && $this->session->get('proxy_to')) {
                continue;
            }
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
 
        $result = lhTextConv::smilesSubstitutions($result);
        return $result;
    }
}
