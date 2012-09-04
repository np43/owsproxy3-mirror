<?php

namespace OwsProxy3\CoreBundle\Component;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use OwsProxy3\CoreBundle\Component\Url;
use OwsProxy3\CoreBundle\Event\AfterProxyEvent;
use OwsProxy3\CoreBundle\Event\BeforeProxyEvent;
use Buzz\Browser;
use Buzz\Client\Curl;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * WMS Proxy
 *
 * @author A.R.Pour
 */
class WmsProxy {
    protected $container;
    
    /**
     * @param Url $url 
     */
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }
    
    /**
     *
     * @return \Symfony\Component\HttpFoundation\Response 
     */
    public function handle(Url $url) {
        $response = new Response();
        $browser = new Browser(new \Buzz\Client\Curl());
        $curl = $browser->getClient()->getCurl();
        $proxy_conf = $this->container->getParameter("owsproxy3.proxy");

        if($proxy_conf['host'] !== null) {
            $logger = $this->container->get('logger');
            $logger->info('WmsProxy handle- Proxy:'.print_r($proxy_conf,true));
            curl_setopt($curl, CURLOPT_PROXY, $proxy_conf['host']);
            curl_setopt($curl, CURLOPT_PROXYPORT, $proxy_conf['port']);
            $logger->info('WmsProxy handle- curl_setopt CURLOPT_PROXY:'.$proxy_conf['host']);
            $logger->info('WmsProxy handle- curl_setopt CURLOPT_PROXYPORT:'.$proxy_conf['port']);
            if($proxy_conf['user'] && $proxy_conf['password']) {
               curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy_conf['user'].':'.$proxy_conf['password']);
               $logger->info('WmsProxy handle- curl_setopt CURLOPT_PROXYUSERPWD:'.$proxy_conf['user'].':'.$proxy_conf['password']);
            }
        }
        try {
            $browserResponse = $browser->get($url->toString());
        } catch(\Exception $e) {
            $browserResponse = new \Buzz\Message\Response();
            $browserResponse->setContent($e->getMessage());
        }
        if($this->getBeforeProxyEventResponse($url, $browserResponse)){
             $response->setContent($browserResponse->getContent());
        } else if($this->getAfterProxyEventResponse($url,$browserResponse)){
             $response->setContent($browserResponse->getContent());
        } else {
            $response->setContent("");
        }

        return $response;
    }
    
    private function getBeforeProxyEventResponse(Url $url, $browserResponse){
        $dispatcher = $this->container->get('event_dispatcher');
        $event = new BeforeProxyEvent($url, $browserResponse);
        $dispatcher->dispatch('owsproxy.before_proxy', $event);
        return $event->getSuccess();
    }
    
    
    private function getAfterProxyEventResponse(Url $url, $browserResponse){
        $dispatcher = $this->container->get('event_dispatcher');
        $event = new AfterProxyEvent($url, $browserResponse);
        $dispatcher->dispatch('owsproxy.after_proxy', $event);
        return $event->getSuccess();
    }
    
//    /**
//     *
//     * @return \Symfony\Component\HttpFoundation\Response 
//     */
//    public function handle(Url $url) {
//        $response = new Response();
//        $browser = new Browser();
//        
//        $dispatcher = $this->container->get('event_dispatcher');
//        
//        try {
//            $event = new BeforeProxyEvent($url);
//            $dispatcher->dispatch('owsproxy.before_proxy', $event);
//        } catch(\RuntimeException $e) {
//            throw new \Exception("502 Bad Gateway");
//        }
//        
//        $browserResponse = $browser->get( $url->toString() );
//        
//        if($browserResponse->isOk()) {
//            $event = new AfterProxyEvent($url, $browserResponse);
//            $dispatcher->dispatch('owsproxy.after_proxy', $event);
//            
//            // Set received headers to our response
//            foreach($browserResponse->getHeaders() as $header) {
//                if(strstr($header, ":") === false) continue;
//                
//                list($key, $val) = explode(":", $header, 2);
//                //$response->headers->set($key, $val);
//            }
//            
//            // Set received content to our response
//            $response->setContent( $browserResponse->getContent() );
//
//        } else {
//            throw new \Exception("502 Bad Gateway");
//        }
//
//        return $response;
//    }
}
