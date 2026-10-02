<?php declare(strict_types=1);

namespace Gta\Tests\Double;

use SoapClient;
use SoapFault;
use SoapServer;

/**
 * SoapClient que entrega o envelope XML a um SoapServer no mesmo processo, em vez de
 * usar a rede. O teste exercita o ciclo SOAP real (WSDL, serialização, faults) sem
 * depender de um servidor HTTP no ar.
 */
final class SoapClientEmProcesso extends SoapClient
{
    public int $requisicoes = 0;

    /**
     * @param int $falhasDeTransporte quantas requisições iniciais simulam falha de rede (timeout)
     */
    public function __construct(
        private readonly string $caminhoWsdl,
        private readonly object $servico,
        private int $falhasDeTransporte = 0,
    ) {
        parent::__construct($caminhoWsdl, [
            'location'   => 'http://em-processo/',
            'cache_wsdl' => WSDL_CACHE_NONE,
            'exceptions' => true,
        ]);
    }

    public function __doRequest(string $request, string $location, string $action, int $version, bool $oneWay = false): string
    {
        $this->requisicoes++;

        if ($this->falhasDeTransporte > 0) {
            $this->falhasDeTransporte--;

            throw new SoapFault('HTTP', 'Error Fetching http headers');
        }

        $servidor = new SoapServer($this->caminhoWsdl, ['cache_wsdl' => WSDL_CACHE_NONE]);
        $servidor->setObject($this->servico);

        ob_start();
        $servidor->handle($request);

        return (string) ob_get_clean();
    }
}
