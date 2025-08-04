<?php
/**
 * Router Class
 * Handles URL routing and dispatches to the appropriate controller
 */
class Router {
    private $routes = [];
    private $defaultController = 'Home';
    private $defaultAction = 'index';
    
    public function __construct() {
        // Define default routes
        $this->addRoute('', $this->defaultController, $this->defaultAction);
    }
    
    /**
     * Add a route to the routing table
     * 
     * @param string $url The route URL
     * @param string $controller The controller name
     * @param string $action The action method
     */
    public function addRoute($url, $controller, $action) {
        $this->routes[$url] = [
            'controller' => $controller,
            'action' => $action
        ];
    }
    
    /**
     * Dispatch the request to the appropriate controller
     */
    public function dispatch() {
        // Get the URL path
        $url = $this->getUrl();
        
        // Look for the route in the routing table
        if (array_key_exists($url, $this->routes)) {
            $controller = $this->routes[$url]['controller'];
            $action = $this->routes[$url]['action'];
        } else {
            // Route not found, use default
            $controller = $this->defaultController;
            $action = $this->defaultAction;
        }
        
        // Add Controller suffix
        $controller .= 'Controller';
        
        // Check if the controller exists
        if (file_exists(BASE_PATH . '/controllers/' . $controller . '.php')) {
            // Include the controller file
            require_once BASE_PATH . '/controllers/' . $controller . '.php';
            
            // Create a new instance of the controller
            $controllerInstance = new $controller();
            
            // Check if the action method exists
            if (method_exists($controllerInstance, $action)) {
                // Call the action method
                $controllerInstance->$action();
            } else {
                // Action not found
                $this->handleError('Action not found');
            }
        } else {
            // Controller not found
            $this->handleError('Controller not found');
        }
    }
    
    /**
     * Get the URL path from the request
     * 
     * @return string The URL path
     */
    private function getUrl() {
        if (isset($_GET['url'])) {
            // Trim trailing slash
            $url = rtrim($_GET['url'], '/');
            // Sanitize URL
            $url = filter_var($url, FILTER_SANITIZE_URL);
            return $url;
        }
        
        return '';
    }
    
    /**
     * Handle errors
     * 
     * @param string $message The error message
     */
    private function handleError($message) {
        // For now, just display the error message
        echo '<h1>Error</h1>';
        echo '<p>' . $message . '</p>';
        exit;
    }
}