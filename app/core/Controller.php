<?php
namespace app\core;

abstract class Controller {
    protected $view;
    protected $model;
    
    public function __construct() {
        $this->view = new View();
    }
    
    protected function render($view, $data = []) {
        return $this->view->render($view, $data);
    }
    
    protected function json($data, $status = 200) {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
    
    protected function redirect($url) {
        header("Location: $url");
        exit;
    }
    
    protected function validate($data, $rules) {
        $validator = new \app\utils\Validation();
        return $validator->validate($data, $rules);
    }
} 