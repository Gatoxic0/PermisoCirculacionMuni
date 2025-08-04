<?php
/**
 * Home Controller
 * Handles the home page and default routes
 */
class HomeController extends Controller {
    /**
     * Index method - default action
     * Displays the home page
     */
    public function index() {
        // Load data for the view
        $data = [
            'title' => 'Welcome to MVC Framework',
            'description' => 'A simple PHP MVC framework'
        ];
        
        // Load the home view with data
        $this->view('home/index', $data);
    }
    
    /**
     * About method
     * Displays the about page
     */
    public function about() {
        $data = [
            'title' => 'About Us',
            'description' => 'Learn more about our application'
        ];
        
        $this->view('home/about', $data);
    }
}