<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of lhChatterBoxInterface
 *
 * @author user
 */
interface lhChatterBoxInterface {
    
    public function __construct($session_id);
    
    // process($text)
    // Предполагаемый формат ответа: [
    //  'text' =>   'текст ответа пользователю',
    //  'hints' =>  [ 'вариант ответа 1', 'вариант ответа 2', ... ]
    // ]
    //
    public function process($text);
    public function setAIProvider($lhaiml_instance);
    public function setScriptProvider($lhcsml_instance);
    
}
