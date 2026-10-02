<?php declare(strict_types=1);

use SoapMock\CadastroAgropecuarioMock;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$wsdl = dirname(__DIR__, 2) . '/resources/wsdl/cadastro-agropecuario.wsdl';

if (isset($_GET['wsdl'])) {
    header('Content-Type: text/xml; charset=utf-8');
    readfile($wsdl);

    return;
}

$servidor = new SoapServer($wsdl, ['cache_wsdl' => WSDL_CACHE_NONE]);
$servidor->setObject(new CadastroAgropecuarioMock());
$servidor->handle();
