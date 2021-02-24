<?php 

class Response {
    private $_success;
    private $_httpStatusCode;
    private $_messages = [];
    private $_data;
    private $_toCache = false;
    private $_responseData = [];    

    public function setSuccess($success){
        $this->_success = $success;
    }

    public function setHttpStatusCode($httpStatusCode){
        $this->_httpStatusCode = $httpStatusCode;
    }

    public function addMessage($message){
        $this->_messages[] = $message;
    }

    public function setData($data){
        $this->_data = $data;
    }  

    public function toCache($toCache){
        $this->_toCache = $toCache;
    }

    public function send(){
        



    }



} // class


