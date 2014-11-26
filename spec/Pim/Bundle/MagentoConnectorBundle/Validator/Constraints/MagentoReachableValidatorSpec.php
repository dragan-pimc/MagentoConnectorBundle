<?php

namespace spec\Pim\Bundle\MagentoConnectorBundle\Validator\Constraints;

use Guzzle\Common\Collection;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Exception\CurlException;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Message\Response;
use Guzzle\Service\ClientInterface;
use PhpSpec\ObjectBehavior;
use Pim\Bundle\MagentoConnectorBundle\Entity\MagentoConfiguration;
use Pim\Bundle\MagentoConnectorBundle\Factory\MagentoSoapClientFactory;
use Pim\Bundle\MagentoConnectorBundle\Validator\Constraints\MagentoReachable;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientInterface;
use Prophecy\Argument;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\ExecutionContextInterface;

class MagentoReachableValidatorSpec extends ObjectBehavior
{
    public function let(ClientInterface $guzzleClient, MagentoSoapClientFactory $soapClientFactory)
    {
        $this->beConstructedWith($guzzleClient, $soapClientFactory);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('\Pim\Bundle\MagentoConnectorBundle\Validator\Constraints\MagentoReachableValidator');
    }

    public function it_adds_a_violation_on_magento_configuration_if_the_url_is_not_reachable(
        ExecutionContextInterface $context,
        MagentoConfiguration $configuration,
        MagentoReachable $constraint,
        RequestInterface $request,
        Collection $collection,
        $soapClientFactory,
        $guzzleClient
    ) {
        $soapUrl = 'http://my wrong url.com/api/soap/?wsdl';
        $guzzleParam = [
            'connect_timeout' => 10,
            'timeout'         => 10,
            'auth'            => [null, null]
        ];

        $configuration->getHttpLogin()->willReturn(null);
        $configuration->getHttpPassword()->willReturn(null);
        $configuration->getSoapUrl()->willReturn($soapUrl);

        $guzzleClient->get($soapUrl, [], $guzzleParam)->shouldBeCalled()->willReturn($request);
        $guzzleClient->send($request)->willThrow(new CurlException('my custom message'));

        $request->getCurlOptions()->willReturn($collection);
        $collection->set(78, 10)->shouldBeCalled();
        $collection->set(13, 10)->shouldBeCalled();

        $context->addViolationAt(
            'MagentoConfiguration',
            'pim_magento_connector.export.validator.url_not_reachable',
            ['my custom message'],
            [
                'Soap URL' => $soapUrl,
                'HTTP login' => null,
                'HTTP password' => null
            ]
        )->shouldBeCalled();

        $soapClientFactory->createMagentoSoapClient($configuration)->shouldNotBeCalled();

        $this->initialize($context);
        $this->validate($configuration, $constraint);
    }

    public function it_adds_a_violation_on_magento_configuration_if_a_web_site_is_reachable_but_returns_a_404(
        ExecutionContextInterface $context,
        MagentoConfiguration $configuration,
        MagentoReachable $constraint,
        RequestInterface $request,
        Collection $collection,
        $soapClientFactory,
        $guzzleClient
    ) {
        $soapUrl = 'http://magento.local/index.php/foo/bar/baz/?wsdl';
        $guzzleParam = [
            'connect_timeout' => 10,
            'timeout'         => 10,
            'auth'            => [null, null]
        ];

        $configuration->getHttpLogin()->willReturn(null);
        $configuration->getHttpPassword()->willReturn(null);
        $configuration->getSoapUrl()->willReturn($soapUrl);

        $guzzleClient->get($soapUrl, [], $guzzleParam)->shouldBeCalled()->willReturn($request);
        $guzzleClient->send($request)->willThrow(new BadResponseException('my custom message'));

        $request->getCurlOptions()->willReturn($collection);
        $collection->set(78, 10)->shouldBeCalled();
        $collection->set(13, 10)->shouldBeCalled();

        $context->addViolationAt(
            'MagentoConfiguration',
            'pim_magento_connector.export.validator.soap_url_not_valid',
            ['my custom message'],
            ['Soap URL' => $soapUrl]
        )->shouldBeCalled();

        $soapClientFactory->createMagentoSoapClient($configuration)->shouldNotBeCalled();

        $this->initialize($context);
        $this->validate($configuration, $constraint);
    }

    public function it_adds_a_violation_on_magento_configuration_if_http_response_content_type_is_not_xml(
        ExecutionContextInterface $context,
        MagentoConfiguration $configuration,
        MagentoReachable $constraint,
        RequestInterface $request,
        Response $response,
        Collection $collection,
        $soapClientFactory,
        $guzzleClient
    ) {
        $soapUrl = 'http://magento.local/index.php/api/soap/?wsdl';
        $guzzleParam = [
            'connect_timeout' => 10,
            'timeout'         => 10,
            'auth'            => [null, null]
        ];

        $configuration->getHttpLogin()->willReturn(null);
        $configuration->getHttpPassword()->willReturn(null);
        $configuration->getSoapUrl()->willReturn($soapUrl);

        $guzzleClient->get($soapUrl, [], $guzzleParam)->shouldBeCalled()->willReturn($request);
        $guzzleClient->send($request)->willReturn($response);

        $request->getCurlOptions()->willReturn($collection);
        $collection->set(78, 10)->shouldBeCalled();
        $collection->set(13, 10)->shouldBeCalled();

        $response->isContentType('text/xml')->willReturn(false);

        $context->addViolationAt(
            'MagentoConfiguration',
            'pim_magento_connector.export.validator.xml_not_valid',
            ['Content type is not XML'],
            ['Soap URL' => $soapUrl]
        )->shouldBeCalled();

        $soapClientFactory->createMagentoSoapClient($configuration)->shouldNotBeCalled();

        $this->initialize($context);
        $this->validate($configuration, $constraint);
    }

    public function it_adds_a_violation_on_magento_configuration_if_soap_can_not_create_a_client_due_to_url(
        ExecutionContextInterface $context,
        MagentoConfiguration $configuration,
        MagentoReachable $constraint,
        RequestInterface $request,
        Response $response,
        Collection $collection,
        MagentoSoapClientInterface $soapClient,
        $soapClientFactory,
        $guzzleClient
    ) {
        $soapUrl = 'http://magento.local/index.php/api/soap/?wsdl';
        $guzzleParam = [
            'connect_timeout' => 10,
            'timeout'         => 10,
            'auth'            => [null, null]
        ];

        $configuration->getHttpLogin()->willReturn(null);
        $configuration->getHttpPassword()->willReturn(null);
        $configuration->getSoapUrl()->willReturn($soapUrl);

        $guzzleClient->get($soapUrl, [], $guzzleParam)->shouldBeCalled()->willReturn($request);
        $guzzleClient->send($request)->willReturn($response);

        $request->getCurlOptions()->willReturn($collection);
        $collection->set(78, 10)->shouldBeCalled();
        $collection->set(13, 10)->shouldBeCalled();

        $response->isContentType('text/xml')->willReturn(true);

        $soapClientFactory->createMagentoSoapClient($configuration)->willThrow(
            new \SoapFault('fault_code', 'failed to load external entity')
        );

        $context->addViolationAt(
            'MagentoConfiguration',
            'pim_magento_connector.export.validator.soap_url_not_valid',
            ['failed to load external entity'],
            ['Soap URL' => $soapUrl]
        )->shouldBeCalled();

        $soapClient->login(Argument::any(), Argument::any())->shouldNotBeCalled();

        $this->initialize($context);
        $this->validate($configuration, $constraint);
    }

    public function it_adds_an_undefined_violation_on_magento_configuration_if_something_wrong_append_during_soap_client_creation(
        ExecutionContextInterface $context,
        MagentoConfiguration $configuration,
        MagentoReachable $constraint,
        RequestInterface $request,
        Response $response,
        Collection $collection,
        MagentoSoapClientInterface $soapClient,
        $soapClientFactory,
        $guzzleClient
    ) {
        $soapUrl = 'http://magento.local/index.php/api/soap/?wsdl';
        $guzzleParam = [
            'connect_timeout' => 10,
            'timeout'         => 10,
            'auth'            => [null, null]
        ];

        $configuration->getSoapUsername()->willReturn('soapUsername');
        $configuration->getSoapApiKey()->willReturn('soapApiKey');
        $configuration->getHttpLogin()->willReturn(null);
        $configuration->getHttpPassword()->willReturn(null);
        $configuration->getSoapUrl()->willReturn($soapUrl);

        $guzzleClient->get($soapUrl, [], $guzzleParam)->shouldBeCalled()->willReturn($request);
        $guzzleClient->send($request)->willReturn($response);

        $request->getCurlOptions()->willReturn($collection);
        $collection->set(78, 10)->shouldBeCalled();
        $collection->set(13, 10)->shouldBeCalled();

        $response->isContentType('text/xml')->willReturn(true);

        $soapClientFactory->createMagentoSoapClient($configuration)->willThrow(
            new \SoapFault('fault_code', 'something wrong append')
        );

        $context->addViolationAt(
            'MagentoConfiguration',
            'pim_magento_connector.export.validator.undefined_exception',
            ['something wrong append'],
            [
                'SOAP username' => 'soapUsername',
                'SOAP API key'  => 'soapApiKey',
                'SOAP URL'      => $soapUrl,
                'HTTP login'    => null,
                'HTTP password' => null
            ]
        )->shouldBeCalled();

        $soapClient->login(Argument::any(), Argument::any())->shouldNotBeCalled();

        $this->initialize($context);
        $this->validate($configuration, $constraint);
    }

    public function it_adds_a_violation_on_magento_configuration_if_soap_login_fails(
        ExecutionContextInterface $context,
        MagentoConfiguration $configuration,
        MagentoReachable $constraint,
        RequestInterface $request,
        Response $response,
        Collection $collection,
        MagentoSoapClientInterface $soapClient,
        $soapClientFactory,
        $guzzleClient
    ) {
        $soapUrl = 'http://magento.local/index.php/api/soap/?wsdl';
        $soapUsername = 'soapUsername';
        $soapApiKey = 'soapApiKey';
        $guzzleParam = [
            'connect_timeout' => 10,
            'timeout'         => 10,
            'auth'            => [null, null]
        ];

        $configuration->getSoapUsername()->willReturn($soapUsername);
        $configuration->getSoapApiKey()->willReturn($soapApiKey);
        $configuration->getHttpLogin()->willReturn(null);
        $configuration->getHttpPassword()->willReturn(null);
        $configuration->getSoapUrl()->willReturn($soapUrl);

        $guzzleClient->get($soapUrl, [], $guzzleParam)->shouldBeCalled()->willReturn($request);
        $guzzleClient->send($request)->willReturn($response);

        $request->getCurlOptions()->willReturn($collection);
        $collection->set(78, 10)->shouldBeCalled();
        $collection->set(13, 10)->shouldBeCalled();

        $response->isContentType('text/xml')->willReturn(true);

        $soapClientFactory->createMagentoSoapClient($configuration)->willReturn($soapClient);

        $soapClient->login($soapUsername, $soapApiKey)->willThrow(new \SoapFault('fault_code', 'access denied'));

        $context->addViolationAt(
            'MagentoConfiguration',
            'pim_magento_connector.export.validator.access_denied',
            ['access denied'],
            ['SOAP username' => $soapUsername, 'SOAP API key' => $soapApiKey]
        )->shouldBeCalled();

        $this->initialize($context);
        $this->validate($configuration, $constraint);
    }

    public function it_adds_an_undefined_violation_on_magento_configuration_if_something_wrong_append_during_soap_client_login(
        ExecutionContextInterface $context,
        MagentoConfiguration $configuration,
        MagentoReachable $constraint,
        RequestInterface $request,
        Response $response,
        Collection $collection,
        MagentoSoapClientInterface $soapClient,
        $soapClientFactory,
        $guzzleClient
    ) {
        $soapUrl = 'http://magento.local/index.php/api/soap/?wsdl';
        $soapUsername = 'soapUsername';
        $soapApiKey = 'soapApiKey';
        $guzzleParam = [
            'connect_timeout' => 10,
            'timeout'         => 10,
            'auth'            => [null, null]
        ];

        $configuration->getSoapUsername()->willReturn($soapUsername);
        $configuration->getSoapApiKey()->willReturn($soapApiKey);
        $configuration->getHttpLogin()->willReturn(null);
        $configuration->getHttpPassword()->willReturn(null);
        $configuration->getSoapUrl()->willReturn($soapUrl);

        $guzzleClient->get($soapUrl, [], $guzzleParam)->shouldBeCalled()->willReturn($request);
        $guzzleClient->send($request)->willReturn($response);

        $request->getCurlOptions()->willReturn($collection);
        $collection->set(78, 10)->shouldBeCalled();
        $collection->set(13, 10)->shouldBeCalled();

        $response->isContentType('text/xml')->willReturn(true);

        $soapClientFactory->createMagentoSoapClient($configuration)->willReturn($soapClient);

        $soapClient->login($soapUsername, $soapApiKey)->willThrow(
            new \SoapFault('fault_code', 'something went wrong')
        );

        $context->addViolationAt(
            'MagentoConfiguration',
            'pim_magento_connector.export.validator.undefined_exception',
            ['something went wrong'],
            [
                'SOAP username' => $soapUsername,
                'SOAP API key'  => $soapApiKey,
                'SOAP URL'      => $soapUrl,
                'HTTP login'    => null,
                'HTTP password' => null
            ]
        )->shouldBeCalled();

        $this->initialize($context);
        $this->validate($configuration, $constraint);
    }
}
