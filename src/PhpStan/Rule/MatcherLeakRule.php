<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Rasuvaeff\Understudy\PhpStan\Collector\CaptureCallCollector;
use Rasuvaeff\Understudy\PhpStan\Collector\HoistedMatcherCollector;
use Rasuvaeff\Understudy\PhpStan\Collector\MatcherCallCollector;
use Rasuvaeff\Understudy\PhpStan\Collector\SpecificationRangeCollector;

/**
 * A matcher written outside a specification.
 *
 * This rule exists because of what `ArgReturnType` does. Typing every
 * matcher as `never` is what lets one stand in for a typed parameter, and it
 * does so everywhere — including in a real call, where the matcher is a
 * plain mistake and PHPStan would otherwise have reported the argument
 * itself. Without this rule the extension would be WEAKER than no extension
 * for that mistake, so it is not an extra.
 *
 * Saying it directly is also better than the type error it replaces: a
 * matcher in a real call reaches the engine as a sentinel object, and the
 * failure it eventually causes names neither the matcher nor the line.
 *
 * @implements Rule<CollectedDataNode>
 *
 * @internal
 */
final class MatcherLeakRule implements Rule
{
    public const string IDENTIFIER = 'understudy.matcherLeak';

    #[\Override]
    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    #[\Override]
    public function processNode(Node $node, Scope $scope): array
    {
        // The collectors declare what they put in; `get()` resolves that
        // through a template psalm cannot follow across the class-string, so
        // the shape is restated here rather than inferred.
        /** @var array<string, list<array{int, int}>> $specifications */
        $specifications = $node->get(SpecificationRangeCollector::class);

        /** @var array<string, list<array{int, int, string}>> $matchersByFile */
        $matchersByFile = $node->get(MatcherCallCollector::class);

        /** @var array<string, list<array{int, int}>> $capturesByFile */
        $capturesByFile = $node->get(CaptureCallCollector::class);

        // A matcher that is stored rather than passed is nobody's argument.
        // Its range joins the specification ranges, because the question this
        // rule asks — "did this matcher reach a real call?" — is answered no
        // for it in the same way.
        /** @var array<string, list<array{int, int}>> $hoistedByFile */
        $hoistedByFile = $node->get(HoistedMatcherCollector::class);

        foreach ($hoistedByFile as $file => $ranges) {
            $specifications[$file] = array_merge($specifications[$file] ?? [], $ranges);
        }

        $errors = [];

        foreach ($matchersByFile as $file => $matchers) {
            foreach ($matchers as [$offset, $line, $matcher]) {
                if ($this->covered($specifications[$file] ?? [], $offset)) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(sprintf(
                    '`Arg::%s()` is a matcher, and this is a real call. A matcher only means '
                    . 'something inside when(), expect(), expectSequence(), verify(), calls(), '
                    . 'lastCall() or verifySequence(); anywhere else it is passed to the code '
                    . 'as a value.',
                    $matcher,
                ))
                    ->identifier(self::IDENTIFIER)
                    ->file($file)
                    ->line($line)
                    ->build();
            }
        }

        // The captor's capture() is a matcher too, typed `never` by
        // `CaptorReturnType` for the same reason `Arg::` matchers are — so a
        // leak of one has to be said here for the same reason theirs is.
        foreach ($capturesByFile as $file => $captures) {
            foreach ($captures as [$offset, $line]) {
                if ($this->covered($specifications[$file] ?? [], $offset)) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(
                    '`capture()` is a matcher, and this is a real call. A captor records only '
                    . 'inside when(), expect(), expectSequence() or verify(); anywhere else its '
                    . 'matcher is passed to the code as a value.',
                )
                    ->identifier(self::IDENTIFIER)
                    ->file($file)
                    ->line($line)
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @param list<array{int, int}> $ranges
     */
    private function covered(array $ranges, int $offset): bool
    {
        foreach ($ranges as [$start, $end]) {
            if ($offset >= $start && $offset <= $end) {
                return true;
            }
        }

        return false;
    }
}
