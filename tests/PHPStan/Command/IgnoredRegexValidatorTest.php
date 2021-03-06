<?php declare(strict_types = 1);

namespace PHPStan\Command;

use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Testing\TestCase;

class IgnoredRegexValidatorTest extends TestCase
{

	public function dataGetIgnoredTypes(): array
	{
		return [
			[
				'#^Call to function method_exists\\(\\) with ReflectionProperty and \'(?:hasType|getType)\' will always evaluate to true\\.$#iu',
				[],
				false,
			],
			[
				'#^Call to function method_exists\\(\\) with ReflectionProperty and \'(?:hasType|getType)\' will always evaluate to true\\.$#',
				[],
				false,
			],
			[
				'#Call to function method_exists\\(\\) with ReflectionProperty and \'(?:hasType|getType)\' will always evaluate to true\\.#',
				[],
				false,
			],
			[
				'#Parameter \#2 $destination of method Nette\\\\Application\\\\UI\\\\Component::redirect\(\) expects string|null, array|string|int given#',
				[
					'null' => 'null, array',
					'string' => 'string',
					'int' => 'int given',
				],
				true,
			],
			[
				'#Parameter \#2 $destination of method Nette\\\\Application\\\\UI\\\\Component::redirect\(\) expects string|null, array|Foo|Bar given#',
				[
					'null' => 'null, array',
				],
				true,
			],
			[
				'#Parameter \#2 $destination of method Nette\\\\Application\\\\UI\\\\Component::redirect\(\) expects string\|null, array\|string\|int given#',
				[],
				true,
			],
			[
				'#Invalid array key type array|string\.#',
				[
					'string' => 'string\\.',
				],
				false,
			],
			[
				'#Invalid array key type array\|string\.#',
				[],
				false,
			],
			[
				'#Array (array<string>) does not accept key resource|iterable\.#',
				[
					'iterable' => 'iterable\.',
				],
				false,
			],
			[
				'#Parameter \#1 $i of method Levels\\\\AcceptTypes\\\\Foo::doBarArray\(\) expects array<int>, array<float|int> given.#',
				[
					'int' => 'int> given.',
				],
				true,
			],
			[
				'#Parameter \#1 \$i of method Levels\\\\AcceptTypes\\\\Foo::doBarArray\(\) expects array<int>|callable, array<float|int> given.#',
				[
					'callable' => 'callable, array<float',
					'int' => 'int> given.',
				],
				false,
			],
			[
				'#Unclosed parenthesis(\)#',
				[],
				false,
			],
		];
	}

	/**
	 * @dataProvider dataGetIgnoredTypes
	 * @param string $regex
	 * @param string[] $expectedTypes
	 * @param bool $expectedHasAnchors
	 */
	public function testGetIgnoredTypes(string $regex, array $expectedTypes, bool $expectedHasAnchors): void
	{
		$grammar = new \Hoa\File\Read('hoa://Library/Regex/Grammar.pp');
		$parser = \Hoa\Compiler\Llk\Llk::load($grammar);
		$validator = new IgnoredRegexValidator($parser, self::getContainer()->getByType(TypeStringResolver::class));

		$result = $validator->validate($regex);
		$this->assertSame($expectedTypes, $result->getIgnoredTypes());
		$this->assertSame($expectedHasAnchors, $result->hasAnchorsInTheMiddle());
	}

}
