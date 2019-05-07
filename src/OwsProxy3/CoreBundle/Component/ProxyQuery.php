<?php

namespace OwsProxy3\CoreBundle\Component;

use Symfony\Component\HttpFoundation\Request;


/**
 * ProxyQuery class provides methods for
 *
 * @author A.R.Pour
 * @author Paul Schmidt
 */
class ProxyQuery
{

    /**
     *
     * @var string[] the parsed url (PHP parse_url()) without get parameters
     */
    protected $rowUrl;

    /**
     *
     * @var string HTTP method (GET/POST)
     */
    protected $method;

    /**
     *
     * @var array the GET parameters
     */
    protected $getParams;

    /**
     *
     * @var array the POST parameter
     */
    protected $postParams;

    /**
     *
     * @var string the POST content
     */
    protected $content;

    /**
     *
     * @var array the query headers
     */
    protected $headers;

    /**
     * Creates an instance from parameters
     *
     * @param string $url the url
     * @param string $user the user name for basic authentication
     * @param string $password the user password for basic authentication
     * @param array $headers the HTTP headers
     * @param array $getParams the GET parameters
     * @param array $postParams the POST parameters
     * @param string $content the POST content
     * @return ProxyQuery a new instance
     * @throws \InvalidArgumentException for invalid url
     */
    public static function createFromUrl($url, $user = null, $password = null,
            $headers = array(), $getParams = array(), $postParams = array(),
            $content = null)
    {
        $rowUrl = parse_url($url);
        if (empty($rowUrl["host"])) {
            throw new \InvalidArgumentException("Missing host name");
        }
        if ($user !== null)
        {
            $rowUrl["user"] = $user;
            $rowUrl["pass"] = $password === null ? "" : $password;
        }
        $getParamsHelp = array();
        if (isset($rowUrl["query"]))
        {
            parse_str($rowUrl["query"], $getParamsHelp);
            unset($rowUrl["query"]);
        }
        $getParams = array_merge($getParamsHelp, $getParams);
        $method    = Utils::$METHOD_GET;
        if ($content !== null || count($postParams) > 0)
        {
            $method = Utils::$METHOD_POST;
        }

        $headers['Host'] = $rowUrl['host'];

        return new ProxyQuery($rowUrl, $method, $content, $getParams,
                $postParams, $headers);
    }

    /**
     * Creates an instance
     *
     * @param Request $request
     * @return ProxyQuery a new instance
     * @throws \InvalidArgumentException for invalid url
     */
    public static function createFromRequest(Request $request)
    {
        $rowUrl = parse_url($request->query->get(Utils::$PARAMETER_URL));
        if (empty($rowUrl["host"])) {
            throw new \InvalidArgumentException("Missing host name");
        }
        $getParams = array();
        if (isset($rowUrl["query"]))
        {
            parse_str($rowUrl["query"], $getParams);
            unset($rowUrl["query"]);
        }
        $extraGetParams = $request->query->all();
        unset($extraGetParams[Utils::$PARAMETER_URL]);

        $content    = $request->getContent() ?: null;
        $postParams = $request->request->all();
        if ($content || $postParams) {
            $method     = Utils::$METHOD_POST;
            // if url containts more get parameters
            $postParams = array_merge($postParams, $extraGetParams);
        } else {
            $method = Utils::$METHOD_GET;
            $getParams = array_merge($getParams, $extraGetParams);
        }
        $headers = Utils::getHeadersFromRequest($request);

        $headers['Host'] = $rowUrl['host'];

        return new ProxyQuery($rowUrl, $method, $content, $getParams,
                $postParams, $headers);
    }

    /**
     * Creates an instance
     *
     * @param array $rowUrl the parsed url (parse_url()) without "query"
     * @param string $method the GET/POST HTTP method
     * @param string $content the POST content
     * @param array $getParams the GET parameter
     * @param array $postParams the POST parameter
     * @param array $headers the HTTP headers
     */
    private function __construct($rowUrl, $method, $content, $getParams,
            $postParams, $headers)
    {
        $this->rowUrl     = $rowUrl;
        $this->method     = $method;
        $this->content    = $content;
        $this->getParams  = array();
        $this->postParams = array();
        $usedKeys = array();
        foreach ($getParams as $key => $value) {
            $lcKey = strtolower($key);
            if (!in_array($lcKey, $usedKeys)) {
                $this->getParams[$key] = $value;
                $usedKeys[] = $lcKey;
            }
        }
        foreach ($postParams as $key => $value) {
            $lcKey = strtolower($key);
            if (!in_array($lcKey, $usedKeys)) {
                $this->postParams[$key] = $value;
                $usedKeys[] = $lcKey;
            }
        }
        $this->headers = $headers;
    }

    /**
     * Returns the query string for POST request
     *
     * @return string the query string for POST request
     */
    public function getPostQueryString()
    {
        return http_build_query($this->postParams);
    }

    /**
     * Returns the POST content
     *
     * @return string content
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Returns the GET/POST method
     *
     * @return string method
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Returns the row url (without GET parameter)
     * @return string[]
     */
    public function getRowUrl()
    {
        return $this->rowUrl;
    }

    /**
     * Returns the headers
     *
     * @return array headers
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Generats the url for HTTP GET
     *
     * @return string the HTTP GET url
     */
    public function getGetUrl()
    {
        $scheme = empty($this->rowUrl["scheme"]) ? "http://" : $this->rowUrl["scheme"] . "://";
        $user   = empty($this->rowUrl["user"]) ? "" : $this->rowUrl["user"];
        $pass   = empty($this->rowUrl["pass"]) ? "" : $this->rowUrl["pass"];

        // if pass is there, put a : between user and pass (user:pass)
        if (!empty($pass)) {
            $user =  rawurlencode($user) .  ":";
        }

        // if user and password are there, put a @ after pass, so that user:pass@host will be constructed
        if (!empty($user) || !empty($pass)) {
            $pass = rawurlencode($pass) . "@";
        }

        $host = $this->rowUrl["host"];
        $port = empty($this->rowUrl["port"]) ? "" : ":" . $this->rowUrl["port"];

        $path = empty($this->rowUrl["path"]) ? "" : $this->rowUrl["path"];

        $urlquery = "";
        if (count($this->getParams) > 0)
        {
            $urlquery = "?" . http_build_query($this->getParams);
        }
        return $scheme . $user . $pass . $host . $port . $path . $urlquery;
    }

    public function getServiceType()
    {
        foreach ($this->getParams as $key => $value) {
            if (strtolower($key) === 'service') {
                return $value;
            }
        }
        foreach ($this->postParams as $key => $value) {
            if (strtolower($key) === 'service') {
                return $value;
            }
        }
        return null;
    }
}
