<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Backend extends CI_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->library('session');
		
		// Set user's selected language.        
        if ($this->session->userdata('language')) {
        	$this->config->set_item('language', $this->session->userdata('language'));
        	$this->lang->load('translations', $this->session->userdata('language'));
        } else {
        	$this->lang->load('translations', $this->config->item('language')); // default
        }	
    }
    
    /**
     * Display the main backend page.
     * 
     * This method displays the main backend page. All users login permission can 
     * view this page which displays a calendar with the events of the selected 
     * provider or service. If a user has more priviledges he will see more menus  
     * at the top of the page.
     * 
     * @param string $appointment_hash If given, the appointment edit dialog will 
     * appear when the page loads.
     */
    public function index($appointment_hash = '') {
        $this->session->set_userdata('dest_url', $this->config->item('base_url') . 'backend');
        if (!$this->hasPrivileges(PRIV_APPOINTMENTS)) return;
        
        $this->load->model('appointments_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $this->load->model('customers_model');
        $this->load->model('settings_model');
        $this->load->model('roles_model');
        $this->load->model('user_model');
        $this->load->model('writers_model');
        
        $view['base_url'] = $this->config->item('base_url');
        $view['user_display_name'] = $this->user_model->get_user_display_name($this->session->userdata('user_id'));
        $view['active_menu'] = PRIV_APPOINTMENTS;
        $view['book_advance_timeout'] = $this->settings_model->get_setting('book_advance_timeout');
        $view['company_name'] = $this->settings_model->get_setting('company_name');
        $view['available_providers'] = $this->providers_model->get_available_providers();
        $view['available_writers'] = $this->writers_model->get_available_writers();
        $view['available_services'] = $this->services_model->get_available_services();
        $view['customers'] = $this->customers_model->get_batch();
        $this->setUserData($view);
        
        if ($this->session->userdata('role_slug') == DB_SLUG_WRITER) {
            $writer = $this->writers_model->get_row($this->session->userdata('user_id'));
            $view['writer_providers'] = $writer['providers'];
        } else {
            $view['writer_providers'] = array();
        }
        
        
        $results = $this->appointments_model->get_batch(array('hash' => $appointment_hash));
        if ($appointment_hash != '' && count($results) > 0) {
            $appointment = $results[0];
            $appointment['customer'] = $this->customers_model->get_row($appointment['id_users_customer']);
            $view['edit_appointment'] = $appointment; // This will display the appointment edit dialog on page load.
        } else {
            $view['edit_appointment'] = NULL;
        }
        
        $this->load->view('backend/header', $view);
        $this->load->view('backend/calendar', $view);
        $this->load->view('backend/footer', $view);
    }
    
    /**
     * Display the backend customers page.
     * 
     * In this page the user can manage all the customer records of the system.
     */
    public function customers() {
        $this->session->set_userdata('dest_url', $this->config->item('base_url') . 'backend/customers');
    	if (!$this->hasPrivileges(PRIV_CUSTOMERS)) return;
    	
        $this->load->model('providers_model');
        $this->load->model('customers_model');
        $this->load->model('services_model');
        $this->load->model('settings_model');
        $this->load->model('user_model');
        
        $view['base_url'] = $this->config->item('base_url');
        $view['user_display_name'] = $this->user_model->get_user_display_name($this->session->userdata('user_id'));
        $view['active_menu'] = PRIV_CUSTOMERS;
        $view['company_name'] = $this->settings_model->get_setting('company_name');
        $view['customers'] = $this->customers_model->get_batch();
        $view['available_providers'] = $this->providers_model->get_available_providers();
        $view['available_services'] = $this->services_model->get_available_services();
        $this->setUserData($view);
        
        $this->load->view('backend/header', $view);
        $this->load->view('backend/customers', $view);
        $this->load->view('backend/footer', $view);
    }
    
    /**
     * Displays the backend services page. 
     * 
     * Here the admin user will be able to organize and create the services 
     * that the user will be able to book appointments in frontend. 
     * 
     * NOTICE: The services that each provider is able to service is managed 
     * from the backend services page. 
     */
    public function services() {
        $this->session->set_userdata('dest_url', $this->config->item('base_url') . 'backend/services');
        if (!$this->hasPrivileges(PRIV_SERVICES)) return;
        
        $this->load->model('customers_model');
        $this->load->model('services_model');
        $this->load->model('settings_model');
        $this->load->model('user_model');
        
        $view['base_url'] = $this->config->item('base_url');
        $view['user_display_name'] = $this->user_model->get_user_display_name($this->session->userdata('user_id'));
        $view['active_menu'] = PRIV_SERVICES;
        $view['company_name'] = $this->settings_model->get_setting('company_name');
        $view['services'] = $this->services_model->get_batch();
        $view['categories'] = $this->services_model->get_all_categories();
        $this->setUserData($view);
        
        $this->load->view('backend/header', $view);
        $this->load->view('backend/services', $view);
        $this->load->view('backend/footer', $view);
    }
    
    /**
     * Display the backend users page.
     * 
     * In this page the admin user will be able to manage the system users. 
     * By this, we mean the provider, writer and admin users. This is also
     * the page where the admin defines which service can each provider provide.
     */
    public function users() {
        $this->session->set_userdata('dest_url', $this->config->item('base_url') . 'backend/users');
        if (!$this->hasPrivileges(PRIV_USERS)) return;
        
        $this->load->model('providers_model');
        $this->load->model('writers_model');
        $this->load->model('admins_model');
        $this->load->model('services_model');
        $this->load->model('settings_model');
        $this->load->model('user_model');
        
        $view['base_url'] = $this->config->item('base_url');
        $view['user_display_name'] = $this->user_model->get_user_display_name($this->session->userdata('user_id'));
        $view['active_menu'] = PRIV_USERS;
        $view['company_name'] = $this->settings_model->get_setting('company_name');
        $view['admins'] = $this->admins_model->get_batch();
        $view['providers'] = $this->providers_model->get_batch();
        $view['writers'] = $this->writers_model->get_batch();
        $view['services'] = $this->services_model->get_batch(); 
        $view['working_plan'] = $this->settings_model->get_setting('company_working_plan');
        $this->setUserData($view);
        
        $this->load->view('backend/header', $view);
        $this->load->view('backend/users', $view);
        $this->load->view('backend/footer', $view);
    }
    
    /**
     * Display the user/system settings.
     * 
     * This page will display the user settings (name, password etc). If current user is
     * an administrator, then he will be able to make change to the current Easy!Appointment 
     * installation (core settings like company name, book timeout etc). 
     */
    public function settings() {
        $this->session->set_userdata('dest_url', $this->config->item('base_url') . 'backend/settings');
        if (!$this->hasPrivileges(PRIV_SYSTEM_SETTINGS, FALSE)
                && !$this->hasPrivileges(PRIV_USER_SETTINGS)) return;
        
        $this->load->model('settings_model');
        $this->load->model('user_model');
        
        $this->load->library('session');        
        $user_id = $this->session->userdata('user_id'); 
        
        $view['base_url'] = $this->config->item('base_url');
        $view['user_display_name'] = $this->user_model->get_user_display_name($user_id);
        $view['active_menu'] = PRIV_SYSTEM_SETTINGS;
        $view['company_name'] = $this->settings_model->get_setting('company_name');
        $view['role_slug'] = $this->session->userdata('role_slug');
        $view['system_settings'] = $this->settings_model->get_settings();
        $view['user_settings'] = $this->user_model->get_settings($user_id);
        $this->setUserData($view);
        
        $this->load->view('backend/header', $view);
        $this->load->view('backend/settings', $view);
        $this->load->view('backend/footer', $view);
    }
    
    /**
     * Check whether current user is logged in and has the required privileges to 
     * view a page. 
     * 
     * The backend page requires different privileges from the users to display pages. Not all
     * pages are avaiable to all users. For example writers should not be able to edit the
     * system users.
     * 
     * @see Constant Definition In application/config/constants.php
     * 
     * @param string $page This argument must match the roles field names of each section 
     * (eg "appointments", "users" ...).
     * @param bool $redirect (OPTIONAL - TRUE) If the user has not the required privileges
     * (either not logged in or insufficient role privileges) then the user will be redirected  
     * to another page. Set this argument to FALSE when using ajax.
     * @return bool Returns whether the user has the required privileges to view the page or
     * not. If the user is not logged in then he will be prompted to log in. If he hasn't the
     * required privileges then an info message will be displayed.
     */
    private function hasPrivileges($page, $redirect = TRUE) {       
        // Check if user is logged in.
        $user_id = $this->session->userdata('user_id');
        if ($user_id == FALSE) { // User not logged in, display the login view.
            if ($redirect) {
                header('Location: ' . $this->config->item('base_url') . 'user/login');
            }
            return FALSE;
        }
        
        // Check if the user has the required privileges for viewing the selected page.
        $role_slug = $this->session->userdata('role_slug');
        $role_priv = $this->db->get_where('ea_roles', array('slug' => $role_slug))->row_array();
        if ($role_priv[$page] < PRIV_VIEW) { // User does not have the permission to view the page.
             if ($redirect) {
                header('Location: ' . $this->config->item('base_url') . 'user/no_privileges');
            }
            return FALSE;
        }
        
        return TRUE;
    }
    
    /**
     * Set the user data in order to be available at the view and js code.
     * 
     * @param array $view Contains the view data. 
     */
    public function setUserData(&$view) {
        $this->load->model('roles_model');
        
        // Get privileges
        $view['user_id'] = $this->session->userdata('user_id');
        $view['user_email'] = $this->session->userdata('user_email');
        $view['role_slug'] = $this->session->userdata('role_slug');
        $view['privileges'] = $this->roles_model->get_privileges($this->session->userdata('role_slug'));
    }

    /**
     * This method will update the installation to the latest available 
     * version in the server. IMPORTANT: The code files must exist in the
     * server, this method will not fetch any new files but will update 
     * the database schema.
     *
     * This method can be used either by loading the page in the browser
     * or by an ajax request. But it will answer with json encoded data.
     */
    public function update() {
        try {
            if (!$this->hasPrivileges(PRIV_SYSTEM_SETTINGS, TRUE)) 
                throw new Exception('You do not have the required privileges for this task!');

            $this->load->library('migration');

            if (!$this->migration->current()) 
                throw new Exception($this->migration->error_string());
            
            echo json_encode(AJAX_SUCCESS);

        } catch(Exception $exc) {
            echo json_encode(array(
                'exceptions' => array(exceptionToJavaScript($exc))
            ));
        }       
    }
}

/* End of file backend.php */
/* Location: ./application/controllers/backend.php */