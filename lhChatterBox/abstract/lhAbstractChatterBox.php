<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of lhAbstractChatterBox
 *
 * @author user
 */
require_once __DIR__ . '/../interface/lhChatterBoxInterface.php';

abstract class lhAbstractChatterBox implements lhChatterBoxInterface {
    
    protected $session; // Экземпляр класса lhSessionFile для хранения данных сессии
    protected $aiml; // Экземпляр класса lhAIML для доступа к данным AIML
    protected $csml; // Экземпляр класса lhCSMS для доступа к скрипту CSML (Chat Script Markup Language)
    protected $text;  // Обрабатываемый текст (реплика пользователя)
    
    public function setAIProvider($lhaiml_instance) {
        $this->aiml = $lhaiml_instance;
    }

    public function setScriptProvider($lhcsml_instance) {
        $this->csml = $lhcsml_instance;
    }

    
}
