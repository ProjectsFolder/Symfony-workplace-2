<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Testservice;

/**
 */
class TestServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \Testservice\Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     */
    public function Do(\Testservice\Request $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/testservice.TestService/Do',
        $argument,
        ['\Testservice\Response', 'decode'],
        $metadata, $options);
    }

}
