<?php
namespace Mobio\Target;

use GuzzleHttp;
use GuzzleHttp\Exception\RequestException;

/**
 * Class Api
 * @package Mobio\Target
 */
class Api
{
    /**
     * @var string
     */
    protected $_uri = 'https://target.my.com';

    /**
     * @var string|null
     */
    protected $_token = null;

    /**
     * @var array
     */
    protected $_errors = [];

    /**
     * @var array
     */
    protected $_logs = [];

    /**
     * @var string
     */
    protected $_requestContents;

    /**
     * last request to myTarget
     * @var array
     */
    protected $_lastRequest = [];

    /**
     * Connection data
     * @var array
     */
    protected $_connectionData = [
        'client_id' => null,
        'client_secret' => null,
        'agency_client_name' => null,
        'type' => 'client'
    ];

    /**
     * Cache
     * @var array
     */
    protected $_cache = [
        'dirName' => '.mobio',
        'dirPath' => null,
        'fileName' => 'myTarget.json',
        'filePath' => null
    ];


    /**
     * Api constructor.
     * @param $clientId
     * @param $clientSecret
     * @param array $options
     */
    public function __construct($clientId, $clientSecret, array $options = [])
    {
        $this->_connectionData['client_id'] = $clientId;
        $this->_connectionData['client_secret'] = $clientSecret;

        $this->_cache['dirPath'] = getenv('HOME') . DIRECTORY_SEPARATOR . $this->_cache['dirName'];
        $this->_cache['filePath'] = $this->_cache['dirPath'] . DIRECTORY_SEPARATOR . $this->_cache['fileName'];

        if (!empty($options['agency_client_name'])) {
            $this->_connectionData['type'] = 'agency';
            $this->_connectionData['agency_client_name'] = $options['agency_client_name'];
        }

        if (!empty($options['uri'])) {
            $this->_uri = $options['uri'];
        }

        if ($this->_getCurrentToken()) {
            $this->_log('Get saved token');
        } else if ($this->_getNewToken()) {
            $this->_log('Get new token');
        } else {
            $this->_error('Can\'t create a new session');
        }
    }

    /**
     * Get token from the cache file
     * @return bool
     */
    protected function _getCurrentToken()
    {
        $data = $this->_findByDate();

        if ($data) {
            $this->_token = $data['token'];
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get new token from the MT
     * @return bool
     */
    protected function _getNewToken()
    {
        $yesterday = date('Y-m-d', time() - 60 * 60 * 24);
        $data = $this->_findByDate($yesterday);

        if (is_array($data) && count($data) > 0 && array_key_exists('refresh_token', $data)) {
            $response = $this->request('/api/v2/oauth2/token.json', 'POST', [
                'client_id' => $this->_connectionData['client_id'],
                'client_secret' => $this->_connectionData['client_secret'],
                'refresh_token' => $data['refresh_token'],
                'grant_type' => 'refresh_token'
            ], false);
        } else {
            $postParams = [
                'client_id' => $this->_connectionData['client_id'],
                'client_secret' => $this->_connectionData['client_secret'],
            ];
            if ($this->_connectionData['type'] == 'agency') {
                $postParams['agency_client_name'] = $this->_connectionData['agency_client_name'];
                $postParams['grant_type'] = 'agency_client_credentials';
            } else {
                $postParams['grant_type'] = 'client_credentials';
            }

            $response = $this->request('/api/v2/oauth2/token.json', 'POST', $postParams, false);
        }

        $sourceContent = '';
        if (is_file($this->_cache['filePath'])) {
            $sourceContent = file_get_contents($this->_cache['filePath']);
        }

        $jsonContent = json_decode($sourceContent);
        if ($jsonContent === null || !is_array($jsonContent)) {
            $jsonContent = [];
        }

        $token = $response->parse()->access_token;

        if ($token) {
            $item = [
                'client_id' => $this->_connectionData['client_id'],
                'token' => $token,
                'refresh_token' => $response->parse()->refresh_token,
                'date' => date('Y-m-d')
            ];

            if ($this->_connectionData['type'] == 'agency') {
                $item['agency_client_name'] = $this->_connectionData['agency_client_name'];
            }

            $jsonContent[] = $item;

            file_put_contents($this->_cache['filePath'], json_encode($jsonContent));
        }

        $this->_token = $token;
        return $this->_token !== null;
    }

    /**
     * @param string $date
     * @return string|null
     */
    protected function _findByDate($date = null)
    {
        $contents = '';
        $result = null;
        $date = $date ? $date : date('Y-m-d');

        if (is_file($this->_cache['filePath'])) {
            $contents = file_get_contents($this->_cache['filePath']);
        } elseif (!is_dir($this->_cache['dirPath'])) {
            mkdir($this->_cache['dirPath'], 0777);
            $this->_log('Create a directory');
        }

        if (strlen($contents) > 0) {
            $arrayData = json_decode($contents, true);

            if (count($arrayData) > 0) {
                foreach ($arrayData as $item) {
                    if ($this->_connectionData['type'] == 'agency') {
                        if ($item['client_id'] === $this->_connectionData['client_id']
                            && $item['agency_client_name'] === $this->_connectionData['agency_client_name']
                            && $item['date'] === $date
                        ) {
                            $result = $item;
                        }
                    } else {
                        if ($item['client_id'] === $this->_connectionData['client_id']
                            && $item['date'] === $date
                            && empty($item['agency_client_name'])
                        ) {
                            $result = $item;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param string $requestUri
     * @param string $method
     * @param null $postParams
     * @param bool $doAuth
     * @return $this
     * @throws \Exception
     */
    public function request($requestUri, $method = 'GET', $postParams = null, $doAuth = true)
    {
        $client = new GuzzleHttp\Client(['base_uri' => $this->_uri, 'verify' => false]);
        $requestData = [];

        if ($postParams && is_array($postParams)) {
            if (array_key_exists('json', $postParams)) {
                $requestData['json'] = $postParams['json'];
            } else {
                $requestData['form_params'] = $postParams;
            }
        }
        if ($doAuth) {
            $requestData['headers'] = ['Authorization' => 'Bearer ' . $this->_token];
        }

        try {
            $response = $client->request($method, $requestUri, $requestData);
            $content  = $response->getBody()->getContents();

            $this->_lastRequest = [
                'method'      => $method,
                'requestUri'  => $requestUri,
                'requestData' => $requestData,
                'responseCode'=> $response->getStatusCode(),
                'responseBody'=> $content,
            ];

            if ($response->getStatusCode() < 200 || $response->getStatusCode() > 200) {
                $this->_error('Error: call to URL ' . $this->_uri . ' failed with status '
                    . $response->getStatusCode());
            }
            $this->_requestContents = $content;
        } catch (RequestException $ex) {
            $this->_error($ex->getMessage());
        }

        return $this;
    }

    /**
     * Parse JSON object
     * @param bool $asArray
     * @return mixed
     */
    public function parse($asArray = false)
    {
        if ($this->_error()) {
            return $this->showErrors();
        }

        if (!$this->_requestContents) {
            $this->_error('You must use toArray after the request method');
            return $this->showErrors();
        }

        $decoded = json_decode($this->_requestContents, $asArray);
        if (is_null($decoded)) {
            $msg = 'Error message: Json was not decoded';
            $msg .= PHP_EOL;
            $msg .= PHP_EOL;
            $msg .= 'Request is: ';
            $msg .= PHP_EOL;
            $msg .= var_export($this->_lastRequest, true);
            echo $msg;
        }

        return $decoded;
    }

    /**
     * @see parse()
     * @return mixed
     */
    public function toArray()
    {
        return $this->parse(true);
    }

    /**
     * @return string
     */
    public function toString()
    {
        if ($this->_error()) {
            return json_encode($this->showErrors());
        }

        if (!$this->_requestContents) {
            $this->_error('You must use toString after the request method');
            return $this->showErrors();
        }

        return $this->_requestContents;
    }

    /**
     * @return string|null
     */
    public function getToken()
    {
        return $this->_token;
    }

    /**
     * @return string
     */
    public function showErrors()
    {
        $message = "Errors: " . PHP_EOL . "----------" . PHP_EOL;

        foreach ($this->_errors as $error) {
            $message .= '* ' . $error;
        }

        echo "----------" . PHP_EOL . $message;
    }

    /**
     * Add message to the log list
     * @param string|null $message
     * @return array
     */
    protected function _error($message = null)
    {
        if ($message) {
            $this->_errors[] = $message;
        }

        return $this->_errors;
    }

    /**
     * Add message to the log list
     * @param string|null $message
     * @return void
     */
    protected function _log($message)
    {
        if ($message) {
            $this->_logs[] = $message;
        }

        $this->_logs;
    }

    /**
     * Uri setter
     * @param $uri
     */
    public function setUri($uri)
    {
        $this->_uri = $uri;
    }
}
