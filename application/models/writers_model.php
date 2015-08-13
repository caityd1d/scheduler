<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed.'); 

/**
 * Writers Model
 * 
 * Handles the db actions that have to do with writers.
 * 
 * Data Structure
 *      'first_name'
 *      'last_name'
 *      'email'
 *      'mobile_number'
 *      'phone_number'
 *      'address'
 *      'city'
 *      'state'
 *      'zip_code'
 *      'notes'
 *      'id_roles'
 *      'providers' >> array with provider ids that the writer handles
 *      'settings' >> array with the writer settings
 */
class Writers_Model extends CI_Model {
    /**
     * Class Constructor
     */
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Add (insert or update) a writer user record into database.
     * 
     * @param array $writer Contains the writer user data.
     * @return int Returns the record id.
     * @throws Exception When the writer data are invalid (see validate() method).
     */
    public function add($writer) {
        $this->validate($writer);

        if ($this->exists($writer) && !isset($writer['id'])) {
            $writer['id'] = $this->find_record_id($writer);
        }
        
        if (!isset($writer['id'])) {
            $writer['id'] = $this->insert($writer);
        } else {
            $writer['id'] = $this->update($writer);
        }
        
        return intval($writer['id']);
    }
    
    /**
     * Check whether a particular writer record exists in the database.
     * 
     * @param array $writer Contains the writer data. The 'email' value is required to 
     * be present at the moment.
     * @return bool Returns whether the record exists or not.
     * @throws Exception When the 'email' value is not present on the $writer argument.
     */
    public function exists($writer) {
        if (!isset($writer['email'])) {
            throw new Exception('Writer email is not provided: ' . print_r($writer, TRUE));
        }
        
        // This method shouldn't depend on another method of this class.
        $num_rows = $this->db
                ->select('*')
                ->from('ea_users')
                ->join('ea_roles', 'ea_roles.id = ea_users.id_roles', 'inner')
                ->where('ea_users.email', $writer['email'])
                ->where('ea_roles.slug', DB_SLUG_WRITER)
                ->get()->num_rows();
        
        return ($num_rows > 0) ? TRUE : FALSE;
    }
    
     /**
     * Insert a new sercretary record into the database.
     * 
     * @param array $writer Contains the writer data.
     * @return int Returns the new record id.
     * @throws Exception When the insert operation fails.
     */
    public function insert($writer) {
        $this->load->helper('general');
        
        $providers = $writer['providers'];
        unset($writer['providers']);
        $settings = $writer['settings'];
        unset($writer['settings']); 
        
        $writer['id_roles'] = $this->get_writer_role_id();
        
        if (!$this->db->insert('ea_users', $writer)) {
            throw new Exception('Could not insert writer into the database.');
        }
        
        $writer['id'] = intval($this->db->insert_id());
        $settings['salt'] = generate_salt();
        $settings['password'] = hash_password($settings['salt'], $settings['password']);
        
        $this->save_providers($providers, $writer['id']);
        $this->save_settings($settings, $writer['id']);
        
        return $writer['id'];
    }   
    
    /**
     * Update an existing writer record in the database.
     * 
     * @param array $writer Contains the writer record data.
     * @return int Retuns the record id.
     * @throws Exception When the update operation fails.
     */
    public function update($writer) {
        $this->load->helper('general');
        
        $providers = $writer['providers'];
        unset($writer['providers']);
        $settings = $writer['settings'];
        unset($writer['settings']); 
        
        if (isset($settings['password'])) {
            $salt = $this->db->get_where('ea_user_settings', array('id_users' => $writer['id']))->row()->salt;
            $settings['password'] = hash_password($salt, $settings['password']);
        }
        
        $this->db->where('id', $writer['id']);
        if (!$this->db->update('ea_users', $writer)){
            throw new Exception('Could not update writer record.');
        }
        
        $this->save_providers($providers, $writer['id']);
        $this->save_settings($settings, $writer['id']);
        
        return intval($writer['id']);
    }
    
    /**
     * Find the database record id of a writer.
     * 
     * @param array $writer Contains the writer data. The 'email' value is required 
     * in order to find the record id.
     * @return int Returns the record id
     * @throws Exception When the 'email' value is not present on the $writer array.
     */
    public function find_record_id($writer) {
        if (!isset($writer['email'])) {
            throw new Exception('Writer email was not provided: ' . print_r($writer, TRUE));
        }
        
        $result = $this->db
                ->select('ea_users.id')
                ->from('ea_users')
                ->join('ea_roles', 'ea_roles.id = ea_users.id_roles', 'inner')
                ->where('ea_users.email', $writer['email'])
                ->where('ea_roles.slug', DB_SLUG_WRITER)
                ->get();
        
        if ($result->num_rows() == 0) {
            throw new Exception('Could not find writer record id.');
        }
        
        return intval($result->row()->id);
    }
    
    /**
     * Validate writer user data before add() operation is executed.
     * 
     * @param array $writer Contains the writer user data.
     * @return bool Returns the validation result.
     */
    public function validate($writer) {
        $this->load->helper('data_validation');
        
        // If a record id is provided then check whether the record exists in the database.
        if (isset($writer['id'])) {
            $num_rows = $this->db->get_where('ea_users', array('id' => $writer['id']))
                    ->num_rows();
            if ($num_rows == 0) {
                throw new Exception('Given writer id does not exist in database: ' . $writer['id']);
            }
        }

        // Validate 'providers' value datatype (must be array)
        if (isset($writer['providers']) && !is_array($writer['providers'])) {
            throw new Exception('Writer providers value is not an array.');
        }

        // Validate required fields integrity.
        if (!isset($writer['last_name'])
                || !isset($writer['email'])
                || !isset($writer['phone_number'])) { 
            throw new Exception('Not all required fields are provided : ' . print_r($writer, TRUE));
        }

        // Validate writer email address.
        if (!filter_var($writer['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address provided : ' . $writer['email']);
        }
        
        // Check if username exists.
        if (isset($writer['settings']['username'])) {
            $user_id = (isset($writer['id'])) ? $writer['id'] : '';
            if (!$this->validate_username($writer['settings']['username'], $user_id)) {
                throw new Exception ('Username already exists. Please select a different ' 
                        . 'username for this record.');
            }
        }

        // Validate writer password.
        if (isset($writer['settings']['password'])) {
            if (strlen($writer['settings']['password']) < MIN_PASSWORD_LENGTH) {
                throw new Exception('The user password must be at least ' 
                        . MIN_PASSWORD_LENGTH . ' characters long.');
            }
        }
        
        // When inserting a record the email address must be unique.
        $writer_id = (isset($writer['id'])) ? $writer['id'] : '';
        
        $num_rows = $this->db
                ->select('*')
                ->from('ea_users')
                ->join('ea_roles', 'ea_roles.id = ea_users.id_roles', 'inner')
                ->where('ea_roles.slug', DB_SLUG_WRITER)
                ->where('ea_users.email', $writer['email'])
                ->where('ea_users.id <>', $writer_id)
                ->get()
                ->num_rows();
        
        if ($num_rows > 0) {
            throw new Exception('Given email address belongs to another writer record. ' 
                    . 'Please use a different email.');
        }

        return TRUE;
    }
    
    /**
     * Delete an existing writer record from the database.
     * 
     * @param numeric $writer_id The writer record id to be deleted.
     * @return bool Returns the delete operation result.
     * @throws Exception When the $writer_id is not a valid numeric value.
     */
    public function delete($writer_id) {
        if (!is_numeric($writer_id)) {
            throw new Exception('Invalid argument type $writer_id : ' . $writer_id);
        }
                
        $num_rows = $this->db->get_where('ea_users', array('id' => $writer_id))->num_rows();
        if ($num_rows == 0) {
            return FALSE; // Record does not exist in database.
        }
        
        return $this->db->delete('ea_users', array('id' => $writer_id));
    }
    
    /**
     * Get a specific writer record from the database.
     * 
     * @param numeric $writer_id The id of the record to be returned.
     * @return array Returns an array with the writer user data.
     * @throws Exception When the $writer_id is not a valid numeric value.
     * @throws Exception When given record id does not exist in the database.
     */
    public function get_row($writer_id) {
        if (!is_numeric($writer_id)) {
            throw new Exception('$writer_id argument is not a valid numeric value: ' . $writer_id);
        }
        
        // Check if record exists
        if ($this->db->get_where('ea_users', array('id' => $writer_id))->num_rows() == 0) {
            throw new Exception('The given writer id does not match a record in the database.');
        }
        
        $writer = $this->db->get_where('ea_users', array('id' => $writer_id))->row_array();
        
        $writer_providers = $this->db->get_where('ea_writers_providers', 
                array('id_users_writer' => $writer['id']))->result_array();
        $writer['providers'] = array();
        foreach($writer_providers as $writer_provider) {
            $writer['providers'][] = $writer_provider['id_users_provider'];
        }
        
        $writer['settings'] = $this->db->get_where('ea_user_settings', 
                array('id_users' => $writer['id']))->row_array();
        unset($writer['settings']['id_users'], $writer['settings']['salt']);
        
        return $writer;
    }
    
    /**
     * Get a specific field value from the database.
     * 
     * @param string $field_name The field name of the value to be returned.
     * @param numeric $writer_id Record id of the value to be returned.
     * @return string Returns the selected record value from the database.
     * @throws Exception When the $field_name argument is not a valid string.
     * @throws Exception When the $writer_id is not a valid numeric.
     * @throws Exception When the writer record does not exist in the database.
     * @throws Exception When the selected field value is not present on database.
     */
    public function get_value($field_name, $writer_id) {
        if (!is_string($field_name)) {
            throw new Exception('$field_name argument is not a string : ' . $field_name);
        }
        
        if (!is_numeric($writer_id)) {
            throw new Exception('$writer_id argument is not a valid numeric value: ' . $writer_id);
        }
        
        // Check whether the writer record exists. 
        $result = $this->db->get_where('ea_users', array('id' => $writer_id));
        if ($result->num_rows() == 0) {
            throw new Exception('The record with the given id does not exist in the '
                    . 'database : ' . $writer_id);
        }
        
        // Check if the required field name exist in database.
        $provider = $result->row_array();
        if (!isset($provider[$field_name])) {
            throw new Exception('The given $field_name argument does not exist in the ' 
                    . 'database: ' . $field_name);
        }
        
        return $provider[$field_name];
    }
    
    /**
     * Get all, or specific writer records from database.
     * 
     * @param string|array $where_clause (OPTIONAL) The WHERE clause of the query to be executed. 
     * Use this to get specific writer records.
     * @return array Returns an array with writer records.
     */
    public function get_batch($where_clause = '') {
        $role_id = $this->get_writer_role_id();
        
        if ($where_clause != '') {
            $this->db->where($where_clause);
        }
        
        $this->db->where('id_roles', $role_id);
        $batch = $this->db->get('ea_users')->result_array();
        
        // Include every writer providers.
        foreach ($batch as &$writer) {
            $writer_providers = $this->db->get_where('ea_writers_providers', 
                    array('id_users_writer' => $writer['id']))->result_array();
            
            $writer['providers'] = array();
            foreach($writer_providers as $writer_provider) {
                $writer['providers'][] = $writer_provider['id_users_provider'];
            }
            
            $writer['settings'] = $this->db->get_where('ea_user_settings', 
                    array('id_users' => $writer['id']))->row_array();
            unset($writer['settings']['id_users']);
        }        
        
        return $batch;
    }
    
/**
     * CL**** Get the available system writers (all of them 8/13/15).
     * 
     * This method returns the available providers and the services that can 
     * provide.
     * 
     * @return array Returns an array with the providers data.
     * 
     * @deprecated since version 0.5 - Use get_batch() instead.
     */
    public function get_available_writers() {
        // Get provider records from database.
        $this->db
                ->select('ea_users.*')
                ->from('ea_users')  
                ->join('ea_roles', 'ea_roles.id = ea_users.id_roles', 'inner')
                ->where('ea_roles.slug', DB_SLUG_WRITER);
        
        $writers = $this->db->get()->result_array();
        
        // Include each provider services and settings.
        foreach($writers as &$writer) {
            // Services
            $services = $this->db->get_where('ea_services_writers', 
                    array('id_users' => $writer['id']))->result_array();
            $writer['services'] = array();
            foreach($services as $service) {
                $writer['services'][] = $service['id_services'];
            }
            
            // Settings
            $writer['settings'] = $this->db->get_where('ea_user_settings', 
                    array('id_users' => $writer['id']))->row_array();
            unset($writer['settings']['id_users']);
        }
        
        // Return provider records.
        return $writers;
    }





    /**
     * Get the writer users role id. 
     * 
     * @return int Returns the role record id. 
     */
    public function get_writer_role_id() {
        return intval($this->db->get_where('ea_roles', array('slug' => DB_SLUG_WRITER))->row()->id);
    }
    
    /**
     * Save a writer hasndling users.
     * @param array $providers Contains the provider ids that are handled by the writer.
     * @param numeric $writer_id The selected writer record.
     */
    private function save_providers($providers, $writer_id) {
        if (!is_array($providers)) {
            throw new Exception('Invalid argument given $providers: ' . print_r($providers, TRUE));
        }
        
        // Delete old connections
        $this->db->delete('ea_writers_providers', array('id_users_writer' => $writer_id));
        
        if (count($providers) > 0) {
            foreach ($providers as $provider_id) {
                $this->db->insert('ea_writers_providers', array(
                    'id_users_writer' => $writer_id,
                    'id_users_provider' => $provider_id
                ));
            }
        }
    }
    
    /**
     * Save the writer settings (used from insert or update operation).
     * 
     * @param array $settings Contains the setting values.
     * @param numeric $writer_id Record id of the writer.
     */
    private function save_settings($settings, $writer_id) {
        if (!is_numeric($writer_id)) {
            throw new Exception('Invalid $provider_id argument given :' . $writer_id);
        }
        
        if (count($settings) == 0 || !is_array($settings)) {
            throw new Exception('Invalid $settings argument given:' . print_r($settings, TRUE));
        }
        
        // Check if the setting record exists in db.
        $num_rows = $this->db->get_where('ea_user_settings', 
                array('id_users' => $writer_id))->num_rows();
        if ($num_rows == 0) {
            $this->db->insert('ea_user_settings', array('id_users' => $writer_id));
        }
        
        foreach($settings as $name => $value) {
            $this->set_setting($name, $value, $writer_id);
        }
    }
    
    /**
     * Get a providers setting from the database.
     * 
     * @param string $setting_name The setting name that is going to be returned.
     * @param int $writer_id The selected provider id.
     * @return string Returs the value of the selected user setting.
     */
    public function get_setting($setting_name, $writer_id) {
        $provider_settings = $this->db->get_where('ea_user_settings', 
                array('id_users' => $writer_id))->row_array();
        return $provider_settings[$setting_name];
    }
    
    /**
     * Set a provider's setting value in the database. 
     * 
     * The provider and settings record must already exist.
     * 
     * @param string $setting_name The setting's name.
     * @param string $value The setting's value.
     * @param numeric $writer_id The selected provider id.
     */
    public function set_setting($setting_name, $value, $writer_id) {
        $this->db->where(array('id_users' => $writer_id));
        return $this->db->update('ea_user_settings', array($setting_name => $value));
    }
    
    /**
     * Validate Records Username 
     * 
     * @param string $username The provider records username.
     * @param numeric $user_id The user record id.
     * @return bool Returns the validation result.
     */
    public function validate_username($username, $user_id) {
        $num_rows = $this->db->get_where('ea_user_settings', 
                array('username' => $username, 'id_users <> ' => $user_id))->num_rows();
        return ($num_rows > 0) ? FALSE : TRUE;
    }
}

/* End of file writers_model.php */
/* Location: ./application/models/writers_model.php */