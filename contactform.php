<?php
/**
 * ContactForm
 *
 * A contact form that uses Wordpress mail system.
 *
 * Version: 0.0.1
 */

class contactForm {

  /**
   * @var $reciever_email, string
   *  The email address which is to recieve the email.
   */
  public $reciever_email = null;

  /**
   * @var $errors, array
   * A list of messages that will be displayed if there is an error.
   */
   public $errors = array();

  /**
   * @var $success, array
   * A list of messages that will be displayed if there is a success.
   */
   public $success = array();

   /**
    * @var $default, array
    * An array of default options.
    */

    private $defaults = [

      /**
       * Set the name of the submit button
       */
      "submit_button_name" => 'contact_form_submit',

      /**
       * Validation types
       */
       "validation_info" => [
         'first_name' => FILTER_SANITIZE_STRING,
         'last_name'  => FILTER_SANITIZE_STRING,
         'email'      => FILTER_VALIDATE_EMAIL,
         'message'    => FILTER_SANITIZE_STRING
       ],

       /**
        * Not required
        */
        "not_required" => array(
          'contact_form_submit'
        ),

        /**
         * Ignored fields
         */
         "ignore" => array(
           'g-recaptcha-response'
         ),

         /**
          * Set the subject
          */
          "subject" => "Contact inquery from PlayOn",

          /**
           * Google captcha key
           */
           "recaptchaKey" => null,
    ];

  /**
   * __construct
   *
   * Is called with each newly created instance
   */
   function __construct($user_options){
     // Check if the submit button was set

     $options = $this->handleDefaults($user_options);


     if(isset($options['incoming'][$options["submit_button_name"]])){
       $this->init($options);
     }else{
       return;
     }
   }

   /**
    * handleDefaults
    *
    * Provides function to handle default array and $user_options.
    *
    * @param $user_options array, an array of options provided by the user
    */
   private function handleDefaults($user_options){
    $this->defaults = array_merge($this->defaults, $user_options);
    return $this->defaults;
   }

   /**
    *  init
    *
    * Function that binds all working parts together based on the $options.
    *
    * @param $options array, the options for the app.
    */
   private function init($options){

     //  Perform validation
     $valid = $this->validate($options["incoming"], $options["validation_info"], $options["not_required"]);


     // If the form is valid, continue to submit.
     if($valid){
       $mail_details = $options["incoming"];

       // Check if the user is a robot:
       if(isset($options['recaptchaKey']) AND !empty($options['recaptchaKey'])){
        $valid =  $this->validate_recaptcha($options['recaptchaKey'], $mail_details['g-recaptcha-response']);
       }

       if(!$valid){
         $this->generateMessage("Are you a robot? Our robot senses are tingiling... Please verify that you're not a robot.", "danger", 'g-recaptcha-response');
         return;
       }


       $mail_sent = $this->send_mail( $options['reciever_email'], $mail_details['first_name'], $mail_details['last_name'], $options['subject'], $mail_details['message'], $mail_details['email']);



      //  Handle email success status
       if(!$mail_sent){
         $this->generateMessage('There was an error sending your message. Please try again.', 'danger');
       }else{
         $this->generateMessage('Your message has been sent!', 'success');
       }
     }

   }

   /**
    * send_mail
    *
    * This method sends off the mail using send_mail. At the moment that's all it does
    * but will eventually support templates and other cool features.
    *
    * @param $to string|array, the email address or array of email addresses to send the message to
    * @param $subject string, the subject of the mail being sent
    * @param $message string, the mail body of the message
    * @param $headers string|array, optional additional headers
    * @param $attachments array files to attach
    */
    private function send_mail($to, $first_name, $last_name,  $subject, $message, $from = "", $headers="", $attachments = []){

      // Set the from headers if set
      if(!empty($from)){
        $headers[] = "Reply-To: $first_name  $last_name <$from>";
      }

      // Send the mail
      $success = wp_mail($to, $subject, $message, $headers, $attachments);

      return $success;
    }

   /**
    *  validate
    *
    * Validates array of elements based on the $required array
    *
    * @param $fields array, the posted fields
    * @param $required array, the fields that are not required
    * @return boolean, returns true if all values were found to be valid, false if else.
    */
   private function validate($fields, $validation_info, $not_required = [], $ignore_array){

     /**
      * set ignore array, default to default ignore array if not set.
      */
     $ignore = (isset($ignore_array) AND is_array($ignore_array) ) ? $ignore_array : $this->defaults['ignore'];

     /**
      * Set validation flag
      */
     $valid = true;
     // loop through fields
     foreach($fields as $key => $value){

       if(in_array($key, $ignore))
        continue;
       // Check it it's set and is not required
       if(empty($value) AND in_array($key, $not_required)){
         // is set but not required, continue.
         continue;
       }else{
         // Check if its set
         if(isset($value) AND empty($value)){
           $this->generateMessage($this->rU($key) . " is required.", 'danger', $key);
           $valid = false;
         }else{
           // Validate
           if(!filter_var($value, $validation_info[$key])){
             $this->generateMessage("Please provide a valid " . $this->rU($key), 'danger', $key);
             $valid = false;
           }
         }
       }

     }

     return $valid;

   }


   /**
    *  generateMessage
    *
    * Appends an html message to the appropriate array of messages.
    *
    * @param $message string, message to be displayed.
    * @param $type string, the type of message to be displayed.
    * @return null;
    */
   private function generateMessage($message = "", $type = 'danger', $key = ""){

      if($type == "danger"){
        if(!empty($key)){
          $this->errors[$key] = $message;
        }else{
          array_push($this->errors, $message);
        }

      }else{
        array_push($this->success, $message);
      }

   }

   /**
    *  the_errors
    *
    * Generates a list of errors based on the errors array
    *
    * @param $before string, the content before the message
    * @param $after string, the content after the message
    */
    public function the_errors($before = '<div class="alert alert-errors">', $after = '</div>'){
      if (count($this->errors) < 1){
        return;
      }
    echo $before;

    echo "<ul>";
    foreach($this->errors as $key=>$value){
      echo "<li>" . $value . "</li>";
    }
      echo "</ul>";
      echo $after;
    }

    /**
     *  the_error_class
     *
     * Prints out the $error_class if the input with $element_name is found to be invalid.\
     *
     * @param $element_name string, the name of the element.
     * @param $error_class string, the class to be printed.
     */
     public function the_error_class($element_name, $error_class = 'has-error'){
       if(array_key_exists($element_name, $this->errors)){
         echo $error_class;
       }
     }

     /**
      *  the_error_message
      *
      * Prints out the error message if the input with the $element_name name has an error.
      *
      * @param $element_name
      */
      public function the_error_message($element_name){
        if(array_key_exists($element_name, $this->errors)){
          echo $this->errors[$element_name];
        }
      }


   /**
    *  the_successes
    *
    * Generates a list of successes based on the success array
    *
    * @param $before string, the content before the message
    * @param $after string, the content after the message
    */
    public function the_success($before = "<div class='alert alert-success'>", $after = "</div>"){
      if(count($this->success) > 0){
        echo $before;
        foreach($this->success as $key=>$value){
          echo $value;
        }
        echo $after;
    }
}

    /**
     *  rU
     *
     * Removes underscores from given text.
     *
     * @param $string string, the string to remove underscores from
     */
     private function rU($string){
       return str_replace("_", " ", $string);
     }

     /**
      * validate_recaptcha
      *
      *
      */

      private function validate_recaptcha($private, $rec_response, $g_url = 'https://www.google.com/recaptcha/api/siteverify')
      {
          // Get variables from outside

          $response = file_get_contents($g_url.'?secret='.$private.'&response='.$rec_response.'&remoteip='.$_SERVER['REMOTE_ADDR']);
          $data = json_decode($response);

          if (isset($data->success) and $data->success == true) {
              return true;
          }

          return false;
      }

}
