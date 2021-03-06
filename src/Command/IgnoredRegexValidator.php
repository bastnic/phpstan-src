<?php declare(strict_types = 1);

namespace PHPStan\Command;

use Hoa\Compiler\Llk\Parser;
use Hoa\Compiler\Llk\TreeNode;
use Nette\Utils\Strings;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VerbosityLevel;
use function substr;

class IgnoredRegexValidator
{

	/** @var Parser */
	private $parser;

	/** @var \PHPStan\PhpDoc\TypeStringResolver */
	private $typeStringResolver;

	public function __construct(
		Parser $parser,
		TypeStringResolver $typeStringResolver
	)
	{
		$this->parser = $parser;
		$this->typeStringResolver = $typeStringResolver;
	}

	public function validate(string $regex): IgnoredRegexValidatorResult
	{
		$regex = $this->removeDelimiters($regex);

		try {
			/** @var TreeNode $ast */
			$ast = $this->parser->parse($regex);
		} catch (\Hoa\Exception\Exception $e) {
			return new IgnoredRegexValidatorResult([], false);
		}

		return new IgnoredRegexValidatorResult(
			$this->getIgnoredTypes($ast),
			$this->hasAnchorsInTheMiddle($ast)
		);
	}

	/**
	 * @param TreeNode $ast
	 * @return array<string, string>
	 */
	private function getIgnoredTypes(TreeNode $ast): array
	{
		/** @var TreeNode|null $alternation */
		$alternation = $ast->getChild(0);
		if ($alternation === null) {
			return [];
		}

		if ($alternation->getId() !== '#alternation') {
			return [];
		}

		$types = [];
		foreach ($alternation->getChildren() as $child) {
			$text = $this->getText($child);
			if ($text === null) {
				continue;
			}

			$matches = Strings::match($text, '#^([a-zA-Z0-9]+)[,]?\s*#');
			if ($matches === null) {
				continue;
			}

			try {
				$type = $this->typeStringResolver->resolve($matches[1], null);
			} catch (\PHPStan\PhpDocParser\Parser\ParserException $e) {
				continue;
			}

			if ($type->describe(VerbosityLevel::typeOnly()) !== $matches[1]) {
				continue;
			}

			if ($type instanceof ObjectType) {
				continue;
			}

			$types[$type->describe(VerbosityLevel::typeOnly())] = $text;
		}

		return $types;
	}

	private function removeDelimiters(string $regex): string
	{
		$delimiter = substr($regex, 0, 1);
		$endDelimiterPosition = strrpos($regex, $delimiter);
		if ($endDelimiterPosition === false) {
			throw new \PHPStan\ShouldNotHappenException();
		}

		return substr($regex, 1, $endDelimiterPosition - 1);
	}

	private function getText(TreeNode $treeNode): ?string
	{
		if ($treeNode->getId() === 'token') {
			return $treeNode->getValueValue();
		}

		if ($treeNode->getId() === '#concatenation') {
			$fullText = '';
			foreach ($treeNode->getChildren() as $child) {
				$text = $this->getText($child);
				if ($text === null) {
					continue;
				}

				$fullText .= $text;
			}

			if ($fullText === '') {
				return null;
			}

			return $fullText;
		}

		return null;
	}

	private function hasAnchorsInTheMiddle(TreeNode $ast): bool
	{
		if ($ast->getId() === 'token') {
			$valueArray = $ast->getValue();

			return $valueArray['token'] === 'anchor' && $valueArray['value'] === '$';
		}
		$childrenCount = count($ast->getChildren());
		foreach ($ast->getChildren() as $i => $child) {
			$has = $this->hasAnchorsInTheMiddle($child);
			if (
				$has
				&& ($ast->getId() !== '#concatenation' || $i !== $childrenCount - 1)
			) {
				return true;
			}
		}

		return false;
	}

}
