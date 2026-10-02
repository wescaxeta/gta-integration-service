<?php declare(strict_types=1);

namespace Gta\Http\Handler;

use Gta\Application\EmitirGta;
use Gta\Application\EmitirGtaComando;
use Gta\Domain\Especie;
use Gta\Domain\Finalidade;
use Gta\Http\GtaJson;
use Gta\Http\InputFilter\EmitirGtaInputFilter;
use Gta\Http\RequisicaoInvalida;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class EmitirGtaHandler implements RequestHandlerInterface
{
    public function __construct(
        private EmitirGta $emitirGta,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $chave = trim($request->getHeaderLine('Idempotency-Key'));
        if ($chave === '') {
            throw RequisicaoInvalida::semChaveIdempotencia();
        }

        $corpo = $request->getParsedBody();
        if (!is_array($corpo)) {
            throw RequisicaoInvalida::corpoNaoJson();
        }

        $filtro = new EmitirGtaInputFilter();
        $filtro->setData($corpo);

        if (!$filtro->isValid()) {
            /** @var array<string, list<string>> $erros */
            $erros = array_map(array_values(...), $filtro->getMessages());

            throw RequisicaoInvalida::camposInvalidos($erros);
        }

        $dados     = $filtro->getValues();
        $resultado = $this->emitirGta->executar(new EmitirGtaComando(
            origem: $dados['origem'],
            destino: $dados['destino'],
            especie: Especie::from($dados['especie']),
            quantidade: (int) $dados['quantidade'],
            finalidade: Finalidade::from($dados['finalidade']),
            chaveIdempotencia: $chave,
        ));

        $corpoResposta = GtaJson::de($resultado->gta);

        if ($resultado->repetida) {
            return new JsonResponse($corpoResposta, 200, ['Idempotent-Replayed' => 'true']);
        }

        return new JsonResponse($corpoResposta, 201, ['Location' => '/gtas/' . $resultado->gta->id->toString()]);
    }
}
