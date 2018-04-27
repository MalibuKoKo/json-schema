<?php

namespace Jane\JsonSchema\Generator\Model;

use Jane\JsonSchema\Generator\Naming;
use Jane\JsonSchema\Guesser\Guess\Property;
use PhpParser\Comment\Doc;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;

trait PropertyGenerator
{
    /**
     * The naming service.
     *
     * @return Naming
     */
    abstract protected function getNaming();

    protected function createProperty(Property $property, $namespace, $default = null): Stmt
    {
        $propertyName = $this->getNaming()->getPropertyName($property->getName());
        $propertyStmt = new Stmt\PropertyProperty($propertyName);

        if (null !== $default) {
            $propertyStmt->default = new Expr\ConstFetch(new Name($default));
        }

        return new Stmt\Property(Stmt\Class_::MODIFIER_PROTECTED, [
            $propertyStmt,
        ], [
            'comments' => [$this->createPropertyDoc($property, $namespace)],
        ]);
    }

    protected function createPropertyDoc(Property $property, $namespace): Doc
    {
        $jsonSchema = $property->getObject();

        $docs[] = '/**';
        if( is_callable([$jsonSchema,"getDescription"]) && $jsonSchema->getDescription() ) {
            $docs[] = ' * '.$jsonSchema->getDescription();
        }
        $docs[] = ' * @var '.$property->getType()->getDocTypeHint($namespace);
        if( is_callable([$jsonSchema,"getRequired"]) && $jsonSchema->getRequired() ) {
            $docs[] = ' * @required';
        }
        if( is_callable([$jsonSchema,"getMinLength"]) && !is_null($jsonSchema->getMinLength()) || is_callable([$jsonSchema,"getMaxLength"]) && !is_null($jsonSchema->getMaxLength()) ) {
            $max = is_null($jsonSchema->getMaxLength()) ? '∞' : $jsonSchema->getMaxLength();
            $docs[] = ' * @length('.$jsonSchema->getMinLength().', '.$max.')';
        }
        if( is_callable([$jsonSchema,"getMinimum"]) && !is_null($jsonSchema->getMinimum()) || is_callable([$jsonSchema,"getMaximum"]) && !is_null($jsonSchema->getMaximum()) ) {
            $max = is_null($jsonSchema->getMaximum()) ? '∞' : $jsonSchema->getMaximum();
            $docs[] = ' * @range('.$jsonSchema->getMinimum().', '.$max.')';
        }
        if( is_callable([$jsonSchema,"getPattern"]) && $jsonSchema->getPattern() ) {
            $docs[] = ' * @pattern : '.  $jsonSchema->getPattern();
        }
        if( is_callable([$jsonSchema,"getEnum"]) && $jsonSchema->getEnum() ) {
            $docs[] = ' * @Enum({"'.  implode('", "', $jsonSchema->getEnum()).'"})';
        }
        if( is_callable([$jsonSchema,"getMinItems"]) && !is_null($jsonSchema->getMinItems()) || is_callable([$jsonSchema,"getMaxItems"]) && !is_null($jsonSchema->getMaxItems()) ) {
            $max = is_null($jsonSchema->getMaxItems()) ? '∞' : $jsonSchema->getMaxItems();
            $docs[] = ' * @items('.$jsonSchema->getMinItems().', '.$max.')';
        }
        if( is_callable([$jsonSchema,"getItems"]) && !is_null($jsonSchema->getItems()) && is_callable([$jsonSchema->getItems(),"getEnum"]) && !is_null($jsonSchema->getItems()->getEnum()) ) {
            $docs[] = ' * @Enum({"'.  implode('", "', $jsonSchema->getItems()->getEnum()).'"})';
        }
        if( is_callable([$jsonSchema,"getItems"]) && !is_null($jsonSchema->getItems()) && is_callable([$jsonSchema->getItems(),"getPattern"]) && !is_null($jsonSchema->getItems()->getPattern()) ) {
            $docs[] = ' * @pattern : '.  $jsonSchema->getItems()->getPattern();
        }
        if( is_callable([$jsonSchema,"getMinProperties"]) && !is_null($jsonSchema->getMinProperties()) || is_callable([$jsonSchema,"getMaxProperties"]) && !is_null($jsonSchema->getMaxProperties()) ) {
            $max = is_null($jsonSchema->getMaxProperties()) ? '∞' : $jsonSchema->getMaxProperties();
            $docs[] = ' * @properties('.$jsonSchema->getMinProperties().', '.$max.')';
        }
        
        $docs[] = ' */';
        
        return new Doc(implode("\r\n", $docs));
    }
}
