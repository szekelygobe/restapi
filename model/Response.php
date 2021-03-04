<?php
declare(strict_types=1);

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
        header('Content-type application/json;charset=utf-8');

        if($this->_toCache == true){
            header('Cache-control: max-age=60');
        } else {
            header('Cache-control: no-cache, no-store');
        }

        if(($this->_success !== false && $this->_success !== true) || !is_numeric($this->_httpStatusCode)){
            http_response_code(500);
            $this->_responseData['statusCode'] = 500;
            $this->_responseData['success'] = false;

            $this->addMessage('Response creation error');
            $this->_responseData['messages'] = $this->_messages;
        } else {
            http_response_code($this->_httpStatusCode);
            $this->_responseData['statusCode'] = $this->_httpStatusCode;
            $this->_responseData['success'] = $this->_success;
            $this->_responseData['messages'] = $this->_messages;
            $this->_responseData['data'] = $this->_data;
        }
        echo(json_encode($this->_responseData));
    }

    public static function returnSuccessResponse(
        int $p_code,
        array $p_return_data = null,
        string $p_message = null,
        bool $p_cache = true
    )
    {
        // building and returning response
        $response = new self();
        $response->setHttpStatusCode($p_code);
        $response->setSuccess(true);

        // cache response if needed
        $p_cache
            ? $response->toCache(true)
            : null;
        // returning data if provided
        is_array($p_return_data) && !empty($p_return_data)
            ? $response->setData($p_return_data)
            : null;
        // returning message if provided
        $p_message && strlen($p_message)
            ? $response->addMessage($p_message)
            : null;

        $response->send();
        exit();
    }// end func err message

    public static function returnErrorResponse(int $p_err_code, array $p_message)
    {
        $response = new self();
        $response->setHttpStatusCode($p_err_code);
        $response->setSuccess(false);
        // adding one or multiple messages
        foreach ($p_message as $message) {
            $response->addMessage($message);
        } // foreach
        $response->send();
        exit();
    } // end func err message

} // class


