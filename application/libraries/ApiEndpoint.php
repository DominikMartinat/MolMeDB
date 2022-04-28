<?php 

class ApiEndpoint
{
    /** Endpoint function types */
    const ENDPOINT_INTERNAL = 'INTERNAL';
    const ENDPOINT_PRIVATE = 'PRIVATE';
    const ENDPOINT_PUBLIC = 'PUBLIC';

    private $endpoint_access_types = array
    (
        self::ENDPOINT_INTERNAL,
        self::ENDPOINT_PRIVATE,
        self::ENDPOINT_PUBLIC
    );

    private $class_to_call = NULL;
    private $function_to_call = NULL;
    private $function_params = NULL;
    private $access_types = NULL;
    private $required_method;
    private $requested_path;

    /** @var HeaderParser */
    private $request;

    /**
     * Constructor
     * 
     * @param HeaderParser $request
     */
    public function __construct($request)
    {
        $this->requested_path = $request->requested_endpoint;
        $this->request = $request;

        $this->requested_path = array_map('trim', array_map('strtolower', $this->requested_path));
        
        //Checking if valid method and endpoint are used
        if(!$this->requested_path || count($this->requested_path) < 2)
        {
            ResponseBuilder::not_found('Requested endpoint was not found.');
        }

        //Lowercasing endpoint for validation and error toleration
        if(!$this->check_valid_endpoint($this->requested_path[0]))
        {
            ResponseBuilder::not_found('Specified endpoint does not exist');
        }

        $path = $this->requested_path;
        $path = implode("/", $path);

        //Checking if endpoint was previously loaded
        $endpoint_parser = new Api_endpoint_parser();
        $loaded_endpoint = $endpoint_parser->find($path, $this->request->method_type);

        if(!$loaded_endpoint)
        {
            ResponseBuilder::not_found();
        }

        $this->access_types = $loaded_endpoint['access_types'];

        // Check access
        $access = false;

        if(in_array(ApiEndpoint::ENDPOINT_PUBLIC, $this->access_types))
        {
            $access = true;
        }
        else if(in_array(ApiEndpoint::ENDPOINT_PRIVATE, $this->access_types))
        {
            // TODO
            // Check if valid user info
            // $access = true;
        }

        if(!$access && in_array(ApiEndpoint::ENDPOINT_INTERNAL, $this->access_types))
        {
            $token = $this->request->auth_token;
            $server_token = Config::get('api_access_token');
            $access = ($token && $token === $server_token);

            if(!$server_token)
            {
                ResponseBuilder::server_error('Internal requests are not currently supported.');
            }
        }

        if(!$access)
        {
            ResponseBuilder::unauthorized();
        }

        try
        {   
            $this->load_endpoint_params($loaded_endpoint);
        }
        catch(ApiException $ex)
        {
            ResponseBuilder::bad_request($ex->getMessage());
        }
        catch(Exception $ex)
        {
            ResponseBuilder::server_error($ex->getMessage());
        }
    }



    /**
    * Calls requested endpoint
    * 
    * @return content of endpoint
    */
    public function call()
    {
        //Creating instance of requested class
        $this->class_to_call = new $this->class_to_call();
        //Calling requested function
        $function = $this->function_to_call;
        
        return $this->class_to_call->$function(...$this->function_params);
    }

    /**
     * Returns api endpoint responses
     * 
     * @return array
     */
    public function responses()
    {
        if(!$this->class_to_call)
        {
            return;
        }

        return $this->class_to_call->responses;
    }

    /**
    * Checks if requested endpoint really exists
    * 
    * @author Jaromir Hradil
    * 
    * @param string $endpoint_to_check
    * 
    * @return bool 
    */
    private function check_valid_endpoint($endpoint_to_check)
    {
        $dir = APP_ROOT . 'controller/Api';
        //Getting valid endpoints dynamically
        foreach (new DirectoryIterator($dir) as $file) 
        {
            if($file->isDot())
            {
                continue;
            }

            $existing_endpoint = $file->getFilename();
            $existing_endpoint = strtolower(explode('.', $existing_endpoint)[0]);  

            if($existing_endpoint == $endpoint_to_check)
            {
                return true;
            }   
        }

        return false;
    }

    /**
    * Prepares and checks loaded endpoint
    * 
    * @param array $endpoint_details
    * 
    * @throws Exception
    * @throws ApiException
    * 
    */
    private function load_endpoint_params($endpoint_details)
    {
        $this->class_to_call = $endpoint_details['class_name'];
        $this->function_to_call = $endpoint_details['function_name'];
       
        $remote_params = $this->request->content;
        $remote_params = array_change_key_case($remote_params, CASE_LOWER);
        $params = array();

        //Checking method params
        foreach($endpoint_details['params'] as $param)
        {
            $key = $param['name'];
            $value = NULL;;
            $required =  $param['required'];

            if(!isset($remote_params[$key]))
            {
                if(isset($param['default']))
                {
                    $value = $param['default'];
                }
                else if($required)
                {
                    ResponseBuilder::bad_request(sprintf("Parameter %s is required", $key));                
                }
                else
                {
                    $value = NULL;
                }
            }
            else
            {
                $value = arr::remove_empty_values($remote_params[$key]);
            }

            $params[] = $value;
        }

        $this->function_params = $params;
    }
}   


