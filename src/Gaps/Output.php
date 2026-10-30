<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Gaps;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;
use Override;

/**
 * One output of a model, as the gap report needs to know it.
 *
 * The application describes each output it delivered: what identifies it, which model produced it,
 * how long it is in that model's tokens, what the model did, and where its marking stands in the
 * metadata of the format it went out in. The report reads nothing else, so it assesses the outputs
 * an application tells it about and no others.
 *
 * @implements Arrayable<string, int|string>
 */
final readonly class Output implements Arrayable, JsonSerializable
{
    /**
     * @param  string  $reference  what identifies the output in the application, such as the id of a message
     * @param  string  $model  the model that produced it, under the name the register lists it by
     * @param  int  $tokens  its length in the model's tokens, as the model's provider counts them
     *
     * @throws InvalidArgumentException when the token count is negative
     */
    public function __construct(
        public string $reference,
        public string $model,
        public int $tokens,
        public OutputKind $kind = OutputKind::Generated,
        public Metadata $metadata = Metadata::NotCarried,
    ) {
        if ($tokens < 0) {
            throw new InvalidArgumentException("The output `{$reference}` has {$tokens} tokens. A length is zero or more.");
        }
    }

    /**
     * @return array{reference: string, model: string, tokens: int, kind: string, metadata: string}
     */
    #[Override]
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'model' => $this->model,
            'tokens' => $this->tokens,
            'kind' => $this->kind->value,
            'metadata' => $this->metadata->value,
        ];
    }

    /**
     * @return array{reference: string, model: string, tokens: int, kind: string, metadata: string}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
