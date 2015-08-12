/**
 * This class contains the writers helper class declaration, along with the "writers"  
 * tab event handlers. By deviding the backend/users tab functionality into separate files
 * it is easier to maintain the code.
 * 
 * @class writersHelper
 */
var writersHelper = function() {
    this.filterResults = {}; // Store the results for later use.
};

/**
 * Bind the event handlers for the backend/users "writers" tab.
 */
writersHelper.prototype.bindEventHandlers = function() {
    /**
     * Event: Filter writers Form "Submit"
     * 
     * Filter the writer records with the given key string.
     */
    $('#filter-writers form').submit(function(event) {
        var key = $('#filter-writers .key').val();
        $('#filter-writers .selected-row').removeClass('selected-row');
        BackendUsers.helper.resetForm();
        BackendUsers.helper.filter(key);
        return false;
    });

    /**
     * Event: Clear Filter Results Button "Click"
     */
    $('#filter-writers .clear').click(function() {
        BackendUsers.helper.filter('');
        $('#filter-writers .key').val('');
    });

    /**
     * Event: Filter writer Row "Click"
     * 
     * Display the selected writer data to the user.
     */
    $(document).on('click', '.writer-row', function() {
        if ($('#filter-writers .filter').prop('disabled')) {
            $('#filter-writers .results').css('color', '#AAA');
            return; // exit because we are currently on edit mode
        }

        var writerId = $(this).attr('data-id'); 
        var writer = {};
        $.each(BackendUsers.helper.filterResults, function(index, item) {
            if (item.id === writerId) {
                writer = item;
                return false;
            }
        });
        
        BackendUsers.helper.display(writer);
        $('#filter-writers .selected-row').removeClass('selected-row');
        $(this).addClass('selected-row');
        $('#edit-writer, #delete-writer').prop('disabled', false);
    });

    /**
     * Event: Add New writer Button "Click"
     */
    $('#add-writer').click(function() {
        BackendUsers.helper.resetForm();
        $('#filter-writers button').prop('disabled', true);
        $('#filter-writers .results').css('color', '#AAA');
        
        $('#writers .add-edit-delete-group').hide();
        $('#writers .save-cancel-group').show();
        $('#writers .details').find('input, textarea').prop('readonly', false);
        $('#writer-password, #writer-password-confirm').addClass('required');
        $('#writer-notifications').prop('disabled', false);
        $('#writer-providers input[type="checkbox"]').prop('disabled', false);
    });

    /**
     * Event: Edit writer Button "Click"
     */
    $('#edit-writer').click(function() {
        $('#filter-writers button').prop('disabled', true);
        $('#filter-writers .results').css('color', '#AAA');
        
        $('#writers .add-edit-delete-group').hide();
        $('#writers .save-cancel-group').show();
        $('#writers .details').find('input, textarea').prop('readonly', false);
        $('#writer-password, #writer-password-confirm').removeClass('required');
        $('#writer-notifications').prop('disabled', false);
        $('#writer-providers input[type="checkbox"]').prop('disabled', false);
    });

    /**
     * Event: Delete writer Button "Click"
     */
    $('#delete-writer').click(function() {
        var writerId = $('#writer-id').val();

        var messageBtns = {};
        messageBtns[EALang['delete']] = function() {
            BackendUsers.helper.delete(writerId);
            $('#message_box').dialog('close');
        };
        messageBtns[EALang['cancel']] = function() {
            $('#message_box').dialog('close');
        };

        GeneralFunctions.displayMessageBox(EALang['delete_writer'], 
                EALang['delete_record_prompt'], messageBtns);
    });

    /**
     * Event: Save writer Button "Click"
     */
    $('#save-writer').click(function() {
        var writer = {
            'first_name': $('#writer-first-name').val(),
            'last_name': $('#writer-last-name').val(),
            'email': $('#writer-email').val(),
            'mobile_number': $('#writer-mobile-number').val(),
            'phone_number': $('#writer-phone-number').val(),
            'address': $('#writer-address').val(),
            'city': $('#writer-city').val(),
            'state': $('#writer-state').val(),
            'zip_code': $('#writer-zip-code').val(),
            'notes': $('#writer-notes').val(),
            'settings': {
                'username': $('#writer-username').val(),                    
                'notifications': $('#writer-notifications').hasClass('active')
            }
        };

        // Include writer services.
        writer.providers = [];
        $('#writer-providers input[type="checkbox"]').each(function() {
            if ($(this).prop('checked')) {
                writer.providers.push($(this).attr('data-id'));
            }
        });

        // Include password if changed.
        if ($('#writer-password').val() !== '') {
            writer.settings.password = $('#writer-password').val();
        }

        // Include id if changed.
        if ($('#writer-id').val() !== '') {
            writer.id = $('#writer-id').val();
        }

        if (!BackendUsers.helper.validate(writer)) return;

        BackendUsers.helper.save(writer);
    });

    /**
     * Event: Cancel writer Button "Click"
     * 
     * Cancel add or edit of an writer record.
     */
    $('#cancel-writer').click(function() {
        var id = $('#writer-id').val();
        BackendUsers.helper.resetForm();
        if (id != '') {
            BackendUsers.helper.select(id, true);
        }
    });
};

/**
 * Save writer record to database.
 * 
 * @param {object} writer Contains the admin record data. If an 'id' value is provided
 * then the update operation is going to be executed.
 */
writersHelper.prototype.save = function(writer) {
    ////////////////////////////////////////////////////
    //console.log('writer data to save:', writer);
    ////////////////////////////////////////////////////
    
    var postUrl = GlobalVariables.baseUrl + 'backend_api/ajax_save_writer';
    var postData = { 'writer': JSON.stringify(writer) };
    
    $.post(postUrl, postData, function(response) {
        ////////////////////////////////////////////////////
        //console.log('Save writer Response:', response);
        ////////////////////////////////////////////////////
        if (!GeneralFunctions.handleAjaxExceptions(response)) return;
        Backend.displayNotification(EALang['writer_saved']);
        BackendUsers.helper.resetForm();
        $('#filter-writers .key').val('');
        BackendUsers.helper.filter('', response.id, true);
    }, 'json');
};

/**
 * Delete a writer record from database.
 * 
 * @param {int} id Record id to be deleted. 
 */
writersHelper.prototype.delete = function(id) {
    var postUrl = GlobalVariables.baseUrl + 'backend_api/ajax_delete_writer';
    var postData = { 'writer_id': id };
    
    $.post(postUrl, postData, function(response) {
        //////////////////////////////////////////////////////
        //console.log('Delete writer response:', response);
        //////////////////////////////////////////////////////
        if (!GeneralFunctions.handleAjaxExceptions(response)) return;
        Backend.displayNotification(EALang['writer_deleted']);
        BackendUsers.helper.resetForm();
        BackendUsers.helper.filter($('#filter-writers .key').val());
    }, 'json');
};

/**
 * Validates a writer record.
 * 
 * @param {object} writer Contains the admin data to be validated.
 * @returns {bool} Returns the validation result.
 */
writersHelper.prototype.validate = function(writer) {
    $('#writers .required').css('border', '');
    $('#writer-password, #writer-password-confirm').css('border', '');
    
    try {
        // Validate required fields.
        var missingRequired = false;
        $('#writers .required').each(function() {
            if ($(this).val() == '' || $(this).val() == undefined) {
                $(this).css('border', '2px solid red');
                missingRequired = true;
            }
        });
        if (missingRequired) {
            throw 'Fields with * are  required.';
        }
        
        // Validate passwords.
        if ($('#writer-password').val() != $('#writer-password-confirm').val()) {
            $('#writer-password, #writer-password-confirm').css('border', '2px solid red');
            throw 'Passwords mismatch!';
        }
        
        if ($('#writer-password').val().length < BackendUsers.MIN_PASSWORD_LENGTH
                && $('#writer-password').val() != '') {
            $('#writer-password, #writer-password-confirm').css('border', '2px solid red');
            throw 'Password must be at least ' + BackendUsers.MIN_PASSWORD_LENGTH 
                    + ' characters long.';
        }
        
        // Validate user email.
        if (!GeneralFunctions.validateEmail($('#writer-email').val())) {
            $('#writer-email').css('border', '2px solid red');
            throw 'Invalid email address!';
        }
        
        // Check if username exists
        if ($('#writer-username').attr('already-exists') ==  'true') {
            $('#writer-username').css('border', '2px solid red');
            throw 'Username already exists.';
        } 
        
        return true;
    } catch(exc) {
        $('#writers .form-message').text(exc);
        $('#writers .form-message').show();
        return false;
    }
};

/**
 * Resets the admin tab form back to its initial state. 
 */
writersHelper.prototype.resetForm = function() {
    $('#writers .details').find('input, textarea').val('');
    $('#writers .add-edit-delete-group').show();
    $('#writers .save-cancel-group').hide();
    $('#edit-writer, #delete-writer').prop('disabled', true);
    $('#writers .details').find('input, textarea').prop('readonly', true);
    $('#writers .form-message').hide();    
    $('#writer-notifications').removeClass('active');
    $('#writer-notifications').prop('disabled', true);
    $('#writer-providers input[type="checkbox"]').prop('checked', false);
    $('#writer-providers input[type="checkbox"]').prop('disabled', true);
    $('#writers .required').css('border', '');
    $('#writer-password, #writer-password-confirm').css('border', '');
    
    $('#filter-writers .selected-row').removeClass('selected-row');
    $('#filter-writers button').prop('disabled', false);
    $('#filter-writers .results').css('color', '');
};

/**
 * Display a writer record into the admin form.
 * 
 * @param {object} writer Contains the writer record data.
 */
writersHelper.prototype.display = function(writer) {
    $('#writer-id').val(writer.id);
    $('#writer-first-name').val(writer.first_name);
    $('#writer-last-name').val(writer.last_name);
    $('#writer-email').val(writer.email);
    $('#writer-mobile-number').val(writer.mobile_number);
    $('#writer-phone-number').val(writer.phone_number);
    $('#writer-address').val(writer.address);
    $('#writer-city').val(writer.city);
    $('#writer-state').val(writer.state);
    $('#writer-zip-code').val(writer.zip_code);
    $('#writer-notes').val(writer.notes);
    
    $('#writer-username').val(writer.settings.username);
    if (writer.settings.notifications == true) {
        $('#writer-notifications').addClass('active');
    } else {
        $('#writer-notifications').removeClass('active');
    }
    
    $('#writer-providers input[type="checkbox"]').prop('checked', false);
    $.each(writer.providers, function(index, providerId) {
        $('#writer-providers input[type="checkbox"]').each(function() {
            if ($(this).attr('data-id') == providerId) {
                $(this).prop('checked', true);
            }
        });
    });
};

/**
 * Filters writer records depending a string key.
 * 
 * @param {string} key This is used to filter the writer records of the database.
 * @param {numeric} selectId (OPTIONAL = undefined) If provided then the given id will be 
 * selected in the filter results (only selected, not displayed).
 * @param {bool} display (OPTIONAL = false)
 */
writersHelper.prototype.filter = function(key, selectId, display) {
    if (display == undefined) display = false;
    
    var postUrl = GlobalVariables.baseUrl + 'backend_api/ajax_filter_writers';
    var postData = { 'key': key };
    
    $.post(postUrl, postData, function(response) {
        ////////////////////////////////////////////////////////
        //console.log('Filter writers response:', response);
        ////////////////////////////////////////////////////////
        
        if (!GeneralFunctions.handleAjaxExceptions(response)) return;
        
        BackendUsers.helper.filterResults = response;
        
        $('#filter-writers .results').data('jsp').destroy();
        $('#filter-writers .results').html('');
        $.each(response, function(index, writer) {
            var html = writersHelper.prototype.getFilterHtml(writer);
            $('#filter-writers .results').append(html);
        });
        $('#filter-writers .results').jScrollPane({ mouseWheelSpeed: 70 });
        
        if (response.length == 0) {
            $('#filter-writers .results').html('<em>' + EALang['no_records_found'] + '</em>')
        }
        
        if (selectId != undefined) {
            BackendUsers.helper.select(selectId, display);
        }
    }, 'json');
};

/**
 * Get an writer row html code that is going to be displayed on the filter results list.
 * 
 * @param {object} writer Contains the writer record data.
 * @returns {string} The html code that represents the record on the filter results list.
 */
writersHelper.prototype.getFilterHtml = function(writer) {
    var name = writer.first_name + ' ' + writer.last_name;
    var info = writer.email;
    info = (writer.mobile_number != '' && writer.mobile_number != null)
            ? info + ', ' + writer.mobile_number : info;
    info = (writer.phone_number != '' && writer.phone_number != null)
            ? info + ', ' + writer.phone_number : info;   
            
    var html =
            '<div class="writer-row" data-id="' + writer.id + '">' + 
                '<strong>' + name + '</strong><br>' +
                info + '<br>' + 
            '</div><hr>';

    return html;
};

/**
 * Select a specific record from the current filter results. If the writer id does not exist 
 * in the list then no record will be selected. 
 * 
 * @param {numeric} id The record id to be selected from the filter results.
 * @param {bool} display (OPTIONAL = false) If true then the method will display the record
 * on the form.
 */
writersHelper.prototype.select = function(id, display) {
    if (display == undefined) display = false;
    
    $('#filter-writers .selected-row').removeClass('selected-row');
    
    $('#filter-writers .writer-row').each(function() {
        if ($(this).attr('data-id') == id) {
            $(this).addClass('selected-row');
            return false;
        }
    });
    
    if (display) { 
        $.each(BackendUsers.helper.filterResults, function(index, admin) {
            if (admin.id == id) {
                BackendUsers.helper.display(admin);
                $('#edit-writer, #delete-writer').prop('disabled', false);
                return false;
            }
        });
    }
};