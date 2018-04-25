<?php

namespace Jane\JsonSchema\Generator\Model;

use Jane\JsonSchema\Generator\Naming;
use Jane\JsonSchema\Guesser\Guess\Property;
use Jane\JsonSchema\Guesser\Guess\Type;
use PhpParser\Comment\Doc;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;

trait GetterSetterGenerator
{
    /**
     * The naming service.
     *
     * @return Naming
     */
    abstract protected function getNaming();

    protected function createGetter(Property $property, $namespace, $required = false): Stmt\ClassMethod
    {
        $returnType = $property->getType()->getTypeHint($namespace);

        if ($returnType && !$required) {
            $returnType = '?' . $returnType;
        }

        return new Stmt\ClassMethod(
            // getProperty
            $this->getNaming()->getPrefixedMethodName('get', $property->getName()),
            [
                // public function
                'type' => Stmt\Class_::MODIFIER_PUBLIC,
                'stmts' => [
                    // return $this->property;
                    new Stmt\Return_(
                        new Expr\PropertyFetch(new Expr\Variable('this'), $this->getNaming()->getPropertyName($property->getName()))
                    ),
                ],
                'returnType' => $returnType,
            ], [
                'comments' => [$this->createGetterDoc($property, $namespace)],
            ]
        );
    }

    protected function createSetter(Property $property, $namespace, $required = false): Stmt\ClassMethod
    {
        $stmts = [];
        /* @var $jsonSchema \Jane\JsonSchema\Model\JsonSchema */
        $jsonSchema = $property->getObject();
        $name = $property->getName();
        
        $stmts = array_merge($stmts,$this->generateStmtsByJsonSchema($name, $jsonSchema));
        $stmts[] = new Stmt\Return_(new Expr\Variable('this'));
        
        $setType = $property->getType()->getTypeHint($namespace);

        if ($setType && !$required) {
            $setType = '?' . $setType;
        }
        
        return new Stmt\ClassMethod(
            // setProperty
            $this->getNaming()->getPrefixedMethodName('set', $name),
            [
                // public function
                'type' => Stmt\Class_::MODIFIER_PUBLIC,
                // ($property)
                'params' => [
                    new Param($this->getNaming()->getPropertyName($name), new Expr\ConstFetch(new Name('null')), $setType),
                ],
                'stmts' => $stmts,
            ], [
                'comments' => [$this->createSetterDoc($property, $namespace)],
            ]
        );
    }

    protected function createGetterDoc(Property $property, $namespace): Doc
    {
        return new Doc(sprintf(<<<EOD
/**
 * %s
 *
 * @return %s
 */
EOD
        , $property->getDescription(), $property->getType()->getDocTypeHint($namespace)));
    }

    protected function createSetterDoc(Property $property, $namespace): Doc
    {
        /* @var $property \Jane\JsonSchema\Guesser\Guess\Property */
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
        if( is_callable([$jsonSchema,"getMinProperties"]) && !is_null($jsonSchema->getMinProperties()) || is_callable([$jsonSchema,"getMaxProperties"]) && !is_null($jsonSchema->getMaxProperties()) ) {
            $max = is_null($jsonSchema->getMaxProperties()) ? '∞' : $jsonSchema->getMaxProperties();
            $docs[] = ' * @properties('.$jsonSchema->getMinProperties().', '.$max.')';
        }
        
        $docs[] = ' *';
        $docs[] = ' * @return self';
        $docs[] = ' */';
        
        return new Doc(implode("\r\n", $docs));
    }

    /**
     * @param $name
     * @param \Jane\JsonSchema\Model\JsonSchema  $jsonSchema
     */
    public function generateStmtsByJsonSchema($name, $jsonSchema)
    {
        $stmts = [];
        if ($jsonSchema instanceof \Jane\JsonSchema\Model\JsonSchema ) {
            $affectation = new Expr\Assign(
                    new Expr\PropertyFetch(
                        new Expr\Variable('this'),
                        $this->getNaming()->getPropertyName($name)
                    ), new Expr\Variable($this->getNaming()->getPropertyName($name))
                );
            if( $jsonSchema->getType()=="string" && $jsonSchema->getPattern() ) {
                // preg_match($pattern.'u', $sujet, $resultats, 0,0)
                $paramsPattern = [
                    new \PhpParser\Node\Scalar\String_('#'.$jsonSchema->getPattern().'#'.'u'),
                    new Expr\Variable($this->getNaming()->getPropertyName($name)),
                    new Expr\Variable('resultats'),
                    new \PhpParser\Node\Scalar\LNumber(0),
                    new \PhpParser\Node\Scalar\LNumber(0),
                ];
                $condPattern = new Expr\FuncCall(new Name('preg_match'), $paramsPattern );
                $stmts[] = new Stmt\If_($condPattern, ['stmts'=>[$affectation]]);
            } elseif( !is_null($jsonSchema->getMinLength()) || !is_null($jsonSchema->getMaxLength()) ) {
                if( !is_null($jsonSchema->getMinLength()) ) {
                    $condGreaterOrEqual = new Expr\BinaryOp\GreaterOrEqual(
                        new Expr\FuncCall( new Name('strlen'), [ new Expr\Variable($this->getNaming()->getPropertyName($name)) ] ),
                        new \PhpParser\Node\Scalar\LNumber($jsonSchema->getMinLength())
                    );
                }
                if( !is_null($jsonSchema->getMaxLength()) ) {
                    $condSmallerOrEqual = new Expr\BinaryOp\SmallerOrEqual(
                        new Expr\FuncCall( new Name('strlen'), [ new Expr\Variable($this->getNaming()->getPropertyName($name)) ] ),
                        new \PhpParser\Node\Scalar\LNumber($jsonSchema->getMaxLength())
                    );
                }
                if( !is_null($jsonSchema->getMinLength()) && !is_null($jsonSchema->getMaxLength()) ) {
                    $condSmallerOrEqualAndGreaterOrEqual = new Expr\BinaryOp\LogicalAnd($condGreaterOrEqual,$condSmallerOrEqual);
                    $stmts[] = new Stmt\If_($condSmallerOrEqualAndGreaterOrEqual, ['stmts'=>[$affectation]]);
                } elseif (!is_null($jsonSchema->getMinLength())) {
                    $stmts[] = new Stmt\If_($condGreaterOrEqual, ['stmts'=>[$affectation]]);
                } elseif (!is_null($jsonSchema->getMaxLength())) {
                    $stmts[] = new Stmt\If_($condSmallerOrEqual, ['stmts'=>[$affectation]]);            
                }
            } elseif( !is_null($jsonSchema->getMinimum()) || !is_null($jsonSchema->getMaximum()) ) {
                if( !is_null($jsonSchema->getMinimum()) ) {
                    if( $jsonSchema->getType()=="integer" ) {
                        $param2CondGreaterOrEqual = new \PhpParser\Node\Scalar\LNumber($jsonSchema->getMinimum());
                    } elseif ( $jsonSchema->getType()=="number" ) {
                        $param2CondGreaterOrEqual = new \PhpParser\Node\Scalar\DNumber($jsonSchema->getMinimum());
                    } else {
                        $param2CondGreaterOrEqual = new \PhpParser\Node\Scalar\DNumber($jsonSchema->getMinimum());
                    }
                    $condGreaterOrEqual = new Expr\BinaryOp\GreaterOrEqual(
                        new Expr\Variable($this->getNaming()->getPropertyName($name)),
                        $param2CondGreaterOrEqual
                    );
                }
                if( !is_null($jsonSchema->getMaximum()) ) {
                    if( $jsonSchema->getType()=="integer" ) {
                        $param2CondSmallerOrEqual = new \PhpParser\Node\Scalar\LNumber($jsonSchema->getMaximum());
                    } elseif ( $jsonSchema->getType()=="number" ) {
                        $param2CondSmallerOrEqual = new \PhpParser\Node\Scalar\DNumber($jsonSchema->getMaximum());
                    } else {
                        $param2CondSmallerOrEqual = new \PhpParser\Node\Scalar\DNumber($jsonSchema->getMaximum());
                    }
                    $condSmallerOrEqual = new Expr\BinaryOp\SmallerOrEqual(
                        new Expr\Variable($this->getNaming()->getPropertyName($name)),
                        $param2CondSmallerOrEqual
                    );           
                }
                if( !is_null($jsonSchema->getMinimum()) && !is_null($jsonSchema->getMaximum()) ) {
                    $condSmallerOrEqualAndGreaterOrEqual = new Expr\BinaryOp\LogicalAnd($condGreaterOrEqual,$condSmallerOrEqual);
                    $stmts[] = new Stmt\If_($condSmallerOrEqualAndGreaterOrEqual, ['stmts'=>[$affectation]]);
                } elseif (!is_null($jsonSchema->getMinimum())) {
                    $stmts[] = new Stmt\If_($condGreaterOrEqual, ['stmts'=>[$affectation]]);
                } elseif (!is_null($jsonSchema->getMaximum())) {
                    $stmts[] = new Stmt\If_($condSmallerOrEqual, ['stmts'=>[$affectation]]);            
                }
            } elseif( $jsonSchema->getEnum() ) {
                $condInArray = new Expr\FuncCall(new Name('in_array'), [ new Expr\Variable($this->getNaming()->getPropertyName($name)), $this->normalizeValue($jsonSchema->getEnum()) ] );
                $stmts[] = new Stmt\If_($condInArray, ['stmts'=>[$affectation]]);   
            } elseif( !is_null($jsonSchema->getMinItems()) || !is_null($jsonSchema->getMaxItems()) ) {
                /* @todo : check maxLength: 200, minLength: 1, type:[0 => "string"] */
                if( $items = $jsonSchema->getItems() ) {                   
                }
                if( !is_null($jsonSchema->getMinItems()) ) {
                    $condGreaterOrEqual = new Expr\BinaryOp\GreaterOrEqual(
                        new Expr\FuncCall( new Name('count'), [ new Expr\Variable($this->getNaming()->getPropertyName($name)) ] ),
                        new \PhpParser\Node\Scalar\LNumber($jsonSchema->getMinItems())
                    );
                }
                if( !is_null($jsonSchema->getMaxItems()) ) {
                    $condSmallerOrEqual = new Expr\BinaryOp\SmallerOrEqual(
                        new Expr\FuncCall( new Name('count'), [ new Expr\Variable($this->getNaming()->getPropertyName($name)) ] ),
                        new \PhpParser\Node\Scalar\LNumber($jsonSchema->getMaxItems())
                    );
                }
                $condIsNull = new Expr\FuncCall(new Name('is_null'), [ new Expr\Variable($this->getNaming()->getPropertyName($name)) ] );
                $condIsArray = new Expr\FuncCall(new Name('is_array'), [ new Expr\Variable($this->getNaming()->getPropertyName($name)) ] );
                $isRequiredOrMinItems = (!is_null($jsonSchema->getMinItems()) || $jsonSchema->getRequired());
                if( $isRequiredOrMinItems ) {
                    if( !is_null($jsonSchema->getMinItems()) && !is_null($jsonSchema->getMaxItems()) ) {
                        $condSmallerOrEqualAndGreaterOrEqual = new Expr\BinaryOp\LogicalAnd($condGreaterOrEqual,$condSmallerOrEqual);
                        $condIsArrayAndSmallerOrEqualAndGreaterOrEqual = new Expr\BinaryOp\LogicalAnd($condIsArray,$condSmallerOrEqualAndGreaterOrEqual);
                        $ifs = $condIsArrayAndSmallerOrEqualAndGreaterOrEqual;
                    } elseif (!is_null($jsonSchema->getMinItems())) {
                        $condIsArrayAndreaterOrEqual = new Expr\BinaryOp\LogicalAnd($condIsArray,$condGreaterOrEqual);
                        $ifs = $condIsArrayAndreaterOrEqual;
                    } elseif (!is_null($jsonSchema->getMaxItems())) {
                        $condIsArrayAndSmallerOrEqual = new Expr\BinaryOp\LogicalAnd($condIsArray,$condSmallerOrEqual);
                        $ifs = $condIsArrayAndSmallerOrEqual;
                    }
                    $stmts[] = new Stmt\If_($ifs, ['stmts'=>[$affectation]]);                                        
                } else {
                    if( !is_null($jsonSchema->getMinItems()) && !is_null($jsonSchema->getMaxItems()) ) {
                        $condSmallerOrEqualAndGreaterOrEqual = new Expr\BinaryOp\LogicalAnd($condGreaterOrEqual,$condSmallerOrEqual);
                        $condIsArrayAndSmallerOrEqualAndGreaterOrEqual = new Expr\BinaryOp\LogicalAnd($condIsArray,$condSmallerOrEqualAndGreaterOrEqual);
                        $elseifs = new Stmt\If_($condIsArrayAndSmallerOrEqualAndGreaterOrEqual,[$affectation]);                
                    } elseif (!is_null($jsonSchema->getMinItems())) {
                        $condIsArrayAndreaterOrEqual = new Expr\BinaryOp\LogicalAnd($condIsArray,$condGreaterOrEqual);
                        $elseifs = new Stmt\If_($condIsArrayAndreaterOrEqual,[$affectation]);
                    } elseif (!is_null($jsonSchema->getMaxItems())) {
                        $condIsArrayAndSmallerOrEqual = new Expr\BinaryOp\LogicalAnd($condIsArray,$condSmallerOrEqual);
                        $elseifs = new Stmt\ElseIf_($condIsArrayAndSmallerOrEqual, [$affectation]);
                    }
                    $stmts[] = new Stmt\If_($condIsNull, ['stmts'=>[$affectation],'elseifs'=>[$elseifs]]);                    
                }
                
            }  elseif( !is_null($jsonSchema->getMinProperties()) || !is_null($jsonSchema->getMaxProperties()) ) {
                if( !is_null($jsonSchema->getMinProperties()) ) {
                    $condGreaterOrEqual = new Expr\BinaryOp\GreaterOrEqual(
                        new Expr\FuncCall( new Name('count'), [ new Expr\Variable($this->getNaming()->getPropertyName($name)) ] ),
                        new \PhpParser\Node\Scalar\LNumber($jsonSchema->getMinProperties())
                    );
                }
                if( !is_null($jsonSchema->getMaxProperties()) ) {
                    $condSmallerOrEqual = new Expr\BinaryOp\SmallerOrEqual(
                        new Expr\FuncCall( new Name('count'), [ new Expr\Variable($this->getNaming()->getPropertyName($name)) ] ),
                        new \PhpParser\Node\Scalar\LNumber($jsonSchema->getMaxProperties())
                    );
                }
                $condIsNull = new Expr\FuncCall(new Name('is_null'), [ new Expr\Variable($this->getNaming()->getPropertyName($name)) ] );
                $condIsArray = new Expr\FuncCall(new Name('is_array'), [ new Expr\Variable($this->getNaming()->getPropertyName($name)) ] );
                $isRequiredOrMinProperties = (!is_null($jsonSchema->getMinProperties()) || $jsonSchema->getRequired());
                if( $isRequiredOrMinProperties ) {
                    if( !is_null($jsonSchema->getMinProperties()) && !is_null($jsonSchema->getMaxProperties()) ) {
                        $condSmallerOrEqualAndGreaterOrEqual = new Expr\BinaryOp\LogicalAnd($condGreaterOrEqual,$condSmallerOrEqual);
                        $condIsArrayAndSmallerOrEqualAndGreaterOrEqual = new Expr\BinaryOp\LogicalAnd($condIsArray,$condSmallerOrEqualAndGreaterOrEqual);
                        $ifs = $condIsArrayAndSmallerOrEqualAndGreaterOrEqual;
                    } elseif (!is_null($jsonSchema->getMinProperties())) {
                        $condIsArrayAndreaterOrEqual = new Expr\BinaryOp\LogicalAnd($condIsArray,$condGreaterOrEqual);
                        $ifs = $condIsArrayAndreaterOrEqual;
                    } elseif (!is_null($jsonSchema->getMaxProperties())) {
                        $condIsArrayAndSmallerOrEqual = new Expr\BinaryOp\LogicalAnd($condIsArray,$condSmallerOrEqual);
                        $ifs = $condIsArrayAndSmallerOrEqual;
                    }
                    $stmts[] = new Stmt\If_($ifs, ['stmts'=>[$affectation]]);                                        
                } else {
                    if( !is_null($jsonSchema->getMinProperties()) && !is_null($jsonSchema->getMaxProperties()) ) {
                        $condSmallerOrEqualAndGreaterOrEqual = new Expr\BinaryOp\LogicalAnd($condGreaterOrEqual,$condSmallerOrEqual);
                        $condIsArrayAndSmallerOrEqualAndGreaterOrEqual = new Expr\BinaryOp\LogicalAnd($condIsArray,$condSmallerOrEqualAndGreaterOrEqual);
                        $elseifs = new Stmt\If_($condIsArrayAndSmallerOrEqualAndGreaterOrEqual,[$affectation]);                
                    } elseif (!is_null($jsonSchema->getMinProperties())) {
                        $condIsArrayAndreaterOrEqual = new Expr\BinaryOp\LogicalAnd($condIsArray,$condGreaterOrEqual);
                        $elseifs = new Stmt\If_($condIsArrayAndreaterOrEqual,[$affectation]);
                    } elseif (!is_null($jsonSchema->getMaxProperties())) {
                        $condIsArrayAndSmallerOrEqual = new Expr\BinaryOp\LogicalAnd($condIsArray,$condSmallerOrEqual);
                        $elseifs = new Stmt\ElseIf_($condIsArrayAndSmallerOrEqual, [$affectation]);
                    }
                    $stmts[] = new Stmt\If_($condIsNull, ['stmts'=>[$affectation],'elseifs'=>[$elseifs]]);                    
                }
            } elseif( $jsonSchema->getUniqueItems() && ($items = $jsonSchema->getItems()) && $items->getEnum() ) {
                $condIsArray = new Expr\FuncCall(new Name('is_array'), [ new Expr\Variable($this->getNaming()->getPropertyName($name)) ] );
                $condArrayDiff = new Expr\FuncCall(new Name('array_diff'), [ new Expr\Variable($this->getNaming()->getPropertyName($name)), $this->normalizeValue($jsonSchema->getItems()->getEnum()) ] );
                $condArrayDiffIsEmpty = new Expr\Empty_($condArrayDiff);
                $condIsArrayAndArrayDiffIsEmpty = new Expr\BinaryOp\LogicalAnd($condIsArray,$condArrayDiffIsEmpty);
                $stmts[] = new Stmt\If_($condIsArrayAndArrayDiffIsEmpty, ['stmts'=>[$affectation]]);
            } elseif($jsonSchema->getType()=="boolean") {
                $condIsNull = new Expr\FuncCall(new Name('is_null'), [ new Expr\Variable($this->getNaming()->getPropertyName($name)) ] );
                $condIsBool = new Expr\FuncCall(new Name('is_bool'), [ new Expr\Variable($this->getNaming()->getPropertyName($name)) ] );
                if( ! $jsonSchema->getRequired() ) {
                    $elseifs = new Stmt\ElseIf_($condIsBool, [$affectation]);
                    $stmts[] = new Stmt\If_($condIsNull, ['stmts'=>[$affectation],'elseifs'=>[$elseifs]]); 
                } else {
                    $stmts[] = new Stmt\If_($condIsBool, ['stmts'=>[$affectation]]); 
                }
            } else {
                $stmts[] = $affectation;         
            }
        }
        return $stmts;
    }
    
    /**
     * Normalizes a value: Converts nulls, booleans, integers,
     * floats, strings and arrays into their respective nodes
     *
     * @param mixed $value The value to normalize
     *
     * @return Expr The normalized value
     */
    protected function normalizeValue($value) {
        if ($value instanceof Node) {
            return $value;
        } elseif (is_null($value)) {
            return new Expr\ConstFetch(
                new Name('null')
            );
        } elseif (is_bool($value)) {
            return new Expr\ConstFetch(
                new Name($value ? 'true' : 'false')
            );
        } elseif (is_int($value)) {
            return new \PhpParser\Node\Scalar\LNumber($value);
        } elseif (is_float($value)) {
            return new \PhpParser\Node\Scalar\DNumber($value);
        } elseif (is_string($value)) {
            return new \PhpParser\Node\Scalar\String_($value);
        } elseif (is_array($value)) {
            $items = array();
            $lastKey = -1;
            foreach ($value as $itemKey => $itemValue) {
                // for consecutive, numeric keys don't generate keys
                if (null !== $lastKey && ++$lastKey === $itemKey) {
                    $items[] = new Expr\ArrayItem(
                        $this->normalizeValue($itemValue)
                    );
                } else {
                    $lastKey = null;
                    $items[] = new Expr\ArrayItem(
                        $this->normalizeValue($itemValue),
                        $this->normalizeValue($itemKey)
                    );
                }
            }

            return new Expr\Array_($items);
        } else {
            throw new \LogicException('Invalid value');
        }
    }
}
