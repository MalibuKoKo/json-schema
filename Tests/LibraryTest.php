<?php

namespace Jane\JsonSchema\Tests;

use Jane\JsonSchema\Jane;
use Jane\JsonSchema\Model\JsonSchema;
use Jane\JsonSchema\Registry;
use Jane\JsonSchema\Schema;
use PHPUnit\Framework\TestCase;

class LibraryTest extends TestCase
{
    /**
     * @var Jane
     */
    protected $jane;

    public function setUp()
    {
        $this->jane = Jane::build();
    }

    /**
     * Unique test with ~70% coverage, library generated from json schema must be the same as the library used
     */
    public function testLibrary()
    {
        $registry = new Registry();
        $registry->addSchema(new Schema('tests/data/json-schema.json', 'Joli\\Jane', __DIR__ . "/generated", 'JsonSchema'));

        $this->jane->generate($registry);

        $this->assertTrue(file_exists(__DIR__ . "/generated/Model/JsonSchema.php"));
        $this->assertTrue(file_exists(__DIR__ . "/generated/Normalizer/JsonSchemaNormalizer.php"));
        $this->assertTrue(file_exists(__DIR__ . "/generated/Normalizer/NormalizerFactory.php"));

        $this->assertEquals(
            file_get_contents(__DIR__ . "/../src/Model/JsonSchema.php"),
            file_get_contents(__DIR__ . "/generated/Model/JsonSchema.php")
        );

        $this->assertEquals(
            file_get_contents(__DIR__ . "/../src/Normalizer/JsonSchemaNormalizer.php"),
            file_get_contents(__DIR__ . "/generated/Normalizer/JsonSchemaNormalizer.php")
        );

        $this->assertEquals(
            file_get_contents(__DIR__ . "/../src/Normalizer/NormalizerFactory.php"),
            file_get_contents(__DIR__ . "/generated/Normalizer/NormalizerFactory.php")
        );
    }

    public function testBothWay()
    {
        $serializer = Jane::buildSerializer();

        $json = file_get_contents(__DIR__ . '/data/json-schema.json');
        $schema = $serializer->deserialize($json, 'Jane\JsonSchema\Model\JsonSchema', 'json', ['document-origin' => 'tests/data/json-schema.json']);
        $newJson = $serializer->serialize($schema, 'json');

        $this->assertEquals(json_decode($json), json_decode($newJson));
    }
}
