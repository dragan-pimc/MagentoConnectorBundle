<?php

namespace Pim\Bundle\MagentoConnectorBundle\Validator\Checks;

use Pim\Bundle\MagentoConnectorBundle\Validator\Exception\InvalidUrlException;
use Pim\Bundle\MagentoConnectorBundle\Validator\Exception\NotReachableUrlException;
use Guzzle\Service\ClientInterface;
use Guzzle\Http\Exception\CurlException;

/**
 * Check an url in different way
 *
 * @author    Willy Mesnage <willy.mesnage@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class UrlChecker
{
    /**
     * @var \Guzzle\Service\ClientInterface
     */
    protected $client;

    /**
     * @param \Guzzle\Service\ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Check if the given string is an url
     *
     * @param string $url
     *
     * @return null
     *
     * @throws InvalidUrlException
     */
    public function checkAnUrl($url)
    {
        if (false === filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidUrlException();
        }
    }

    /**
     * Check if the given URL is reachable
     *
     * @param string $url
     *
     * @return null
     *
     * @throws NotReachableUrlException
     */
    public function checkReachableUrl($url)
    {
        $request = $this->client->createRequest('GET', $url);

        try {
            $this->client->send($request);
        } catch (CurlException $ex) {
            throw new NotReachableUrlException();
        }
    }
}
