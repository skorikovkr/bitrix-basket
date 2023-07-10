<?php


namespace Legacy\General;


use Bitrix\Main\Context;
use Bitrix\Main\Diag\Debug;

final class Api
{
    /**
     * @var Api
     */
    private static $instance;
    private $context;
    private $request;
    private $data = [];

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function getInstance(): Api
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::getInstance() instead
     */
    private function __construct()
    {
        $this->context = Context::getCurrent();
        $this->request = $this->context->getRequest();
        $this->fetch();
    }

    /**
     * prevent the instance from being cloned (which would create a second instance of it)
     */
    private function __clone()
    {
    }

    /**
     * prevent from being unserialized (which would create a second instance of it)
     */
    private function __wakeup()
    {
    }

    private function fetchCookies()
    {
        $rawCookies = $this->request->getCookieRawList()->toArray();
        $cookies = [];
        foreach ($rawCookies as $key => $value) {
            $json = json_decode($value, true);
            if (json_last_error() == JSON_ERROR_NONE) {
                $cookies[$key] = $json;
            } else {
                $cookies[$key] = $value;
            }
        }
        $this->data['cookies'] = $cookies;
    }

    private function fetchPost()
    {
        $input = file_get_contents('php://input');
        $json = json_decode($input, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $data = $json;
        } else {
            $data = $this->request->getPostList()->toArray() ?? [];
            $files = $this->request->getFileList()->toArray() ?? [];
            $data = array_merge($data, $files);
        }

        $this->data = array_merge($this->data, $data);
    }

    private function fetchGet()
    {
        $this->data = array_merge($this->data, $this->request->toArray());
    }

    private function fetch()
    {
        $this->fetchGet();
        $this->fetchPost();
        $this->fetchCookies();
    }

    public function execute($class, $method, $data = [])
    {
        try {
            $this->data = array_merge($this->data, $data);
            $return = call_user_func($class.'::'.$method, $this->data);
            $result['status'] = 'ok';
            $result['errorCode'] = 0;
            $result['errorMessage'] = '';
            $result['result'] = $return;
        } catch (\Exception $e) {
            http_response_code(400);
            $result['status'] = 'error';
            $result['errorCode'] = 1;
            $result['errorMessage'] = $e->getMessage();
            $result['result'] = ['message' => $e->getMessage()];
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}