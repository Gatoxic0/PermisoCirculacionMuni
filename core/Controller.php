<?php
/**
 * Base Controller Class
 * All controllers will extend this class
 */
abstract class Controller {
    /**
     * Load and render a view
     * 
     * @param string $view The view file to load
     * @param array $data Data to pass to the view
     * @return void
     */
    protected function view($view, $data = []) {
        // Check if the view file exists
        $viewFile = BASE_PATH . '/views/' . $view . '.php';
        
        if (file_exists($viewFile)) {
            // Extract data to make variables available in the view
            extract($data);
            
            // Include the view file
            require_once $viewFile;
        } else {
            // View not found
            die('View not found: ' . $view);
        }
    }
    
    /**
     * Load a model
     * 
     * @param string $model The model to load
     * @return object The model instance
     */
    protected function model($model) {
        // Check if the model file exists
        $modelFile = BASE_PATH . '/models/' . $model . '.php';
        
        if (file_exists($modelFile)) {
            // Include the model file
            require_once $modelFile;
            
            // Create a new instance of the model
            return new $model();
        } else {
            // Model not found
            die('Model not found: ' . $model);
        }
    }
    
    /**
     * Redirect to another page
     * 
     * @param string $url The URL to redirect to
     * @return void
     */
    protected function redirect($url) {
        header('Location: ' . BASE_URL . '/' . $url);
        exit;
    }
}