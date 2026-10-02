<?php declare(strict_types=1);

namespace Gta\Http\InputFilter;

use Gta\Domain\Especie;
use Gta\Domain\Finalidade;
use Laminas\Filter\StringToLower;
use Laminas\Filter\StringToUpper;
use Laminas\Filter\StringTrim;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Validator\Between;
use Laminas\Validator\Digits;
use Laminas\Validator\InArray;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\Regex;

/**
 * Valida o formato do corpo de POST /gtas. As regras de negócio (saldo, situação
 * da propriedade etc.) ficam no domínio, não aqui.
 *
 * @phpstan-import-type InputSpecification from InputFilterInterface
 * @phpstan-import-type ValidatorSpecification from InputFilterInterface
 *
 * @extends InputFilter<array{origem: string, destino: string, especie: string, quantidade: int|string, finalidade: string}>
 */
final class EmitirGtaInputFilter extends InputFilter
{
    public const int QUANTIDADE_MAXIMA = 10_000;

    public function __construct()
    {
        foreach (['origem', 'destino'] as $campo) {
            $this->add([
                'name'       => $campo,
                'required'   => true,
                'filters'    => [['name' => StringTrim::class], ['name' => StringToUpper::class]],
                'validators' => [
                    $this->obrigatorio(),
                    [
                        'name'    => Regex::class,
                        'options' => [
                            'pattern'  => '/^[A-Z]{2}\d{6}$/',
                            'messages' => [Regex::NOT_MATCH => 'Use a UF seguida de 6 dígitos (ex.: GO000123).'],
                        ],
                    ],
                ],
            ]);
        }

        $this->add($this->opcoes('especie', array_column(Especie::cases(), 'value')));
        $this->add($this->opcoes('finalidade', array_column(Finalidade::cases(), 'value')));

        $this->add([
            'name'       => 'quantidade',
            'required'   => true,
            'validators' => [
                $this->obrigatorio(),
                [
                    'name'                   => Digits::class,
                    'break_chain_on_failure' => true,
                    'options'                => ['messages' => [Digits::NOT_DIGITS => 'Informe um número inteiro.']],
                ],
                [
                    'name'    => Between::class,
                    'options' => [
                        'min'      => 1,
                        'max'      => self::QUANTIDADE_MAXIMA,
                        'messages' => [Between::NOT_BETWEEN => 'Informe entre %min% e %max% animais.'],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param list<string> $permitidos
     *
     * @return InputSpecification
     */
    private function opcoes(string $campo, array $permitidos): array
    {
        return [
            'name'       => $campo,
            'required'   => true,
            'filters'    => [['name' => StringTrim::class], ['name' => StringToLower::class]],
            'validators' => [
                $this->obrigatorio(),
                [
                    'name'    => InArray::class,
                    'options' => [
                        'haystack' => $permitidos,
                        'strict'   => InArray::COMPARE_STRICT,
                        'messages' => [InArray::NOT_IN_ARRAY => 'Valores aceitos: ' . implode(', ', $permitidos) . '.'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return ValidatorSpecification
     */
    private function obrigatorio(): array
    {
        return [
            'name'                   => NotEmpty::class,
            'break_chain_on_failure' => true,
            'options'                => ['messages' => [NotEmpty::IS_EMPTY => 'Campo obrigatório.']],
        ];
    }
}
