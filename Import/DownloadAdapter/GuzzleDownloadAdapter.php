<?php
namespace Giosh94mhz\GeonamesBundle\Import\DownloadAdapter;

use Doctrine\Common\Cache\FilesystemCache;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Pool;
use GuzzleHttp\Event\ProgressEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Psr7\Request;

class GuzzleDownloadAdapter extends AbstractDownloadAdapter
{
    /**
     * @var ClientInterface
     */
    protected $client;

    protected $directory;

    protected $requests;

    protected $downloadsSize;

    /**
     * @param ClientInterface $client Client object
     */
    public function __construct(ClientInterface $client = null)
    {
        $this->requests = array();

        if (! $client) {
            $this->client = new Client();
        } else {
            $this->client = $client;
        }
    }

    public function add($url)
    {
        $destFile = $this->getDestinationPath($url);
        $this->requests[] = array(
            'url' => $url,
            'file' => $destFile
        );

        return $destFile;
    }

    public function requestContentLength()
    {
        if ($this->downloadsSize === null) {
            $requests = array();
            foreach ($this->requests as $request) {
                $requests[] = new Request('HEAD', $request['url']);
            }

            $contentLength = 0;
            $pool = new Pool($this->client, $requests, [
                'complete' => function (CompleteEvent $event) use (&$contentLength) {
                    $contentLength += $event->getRequest()->getHeader('Content-Length');
                }
            ]);

            $promise = $pool->promise();

            $promise->wait();

            $this->downloadsSize = $contentLength;
        }

        return $this->downloadsSize;
    }

    public function download()
    {
        $progressFunctions = null;
        if ($this->getProgressFunction()) {
            $progressFunctions = $this->createProgressFunctions(
                array_fill(0, count($this->requests), 0)
            );
        }

        $client = $this->client;
        $requestsArray = $this->requests;

        $requests = function () use ($client, $requestsArray, $progressFunctions) {
            foreach ($requestsArray as $i => $r) {
                $filePath = $r['file'];
                $url = $r['url'];

                yield function($poolOpts) use ($client, $url, $filePath, $progressFunctions, $i) {
                    $reqOpts = array(
                        'sink' => $filePath,
                    );

                    if ($progressFunctions !== null) {
                        $f = $progressFunctions[$i];

                        $reqOpts['progress'] = function ($dl_total_size, $dl_size_so_far, $ul_total_size, $ul_size_so_far) use ($f) {
                            call_user_func($f, $dl_size_so_far, $dl_total_size);
                        };
                    }

                    if (is_array($poolOpts) && count($poolOpts) > 0) {
                        $reqOpts = array_merge($poolOpts, $reqOpts);
                    }

                    return $client->getAsync($url, $reqOpts);
                };
            }
        };

        $pool = new Pool($this->client, $requests());

        $promise = $pool->promise();


        $promise->wait();
    }

    public function clear()
    {
        $this->requests = array();
    }
}
