<?php

declare(strict_types=1);

namespace Rector\Doctrine\NodeFactory;

use Nette\Utils\Strings;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Property;
use Ramsey\Uuid\Uuid;
use Rector\Doctrine\PhpDocParser\Ast\PhpDoc\PhpDocTagNodeFactory;
use Rector\NodeTypeResolver\PhpDoc\NodeAnalyzer\DocBlockManipulator;
use Rector\PhpParser\Node\NodeFactory;

final class EntityUuidNodeFactory
{
    /**
     * @var PhpDocTagNodeFactory
     */
    private $phpDocTagNodeFactory;

    /**
     * @var DocBlockManipulator
     */
    private $docBlockManipulator;

    /**
     * @var NodeFactory
     */
    private $nodeFactory;

    public function __construct(
        PhpDocTagNodeFactory $phpDocTagNodeFactory,
        DocBlockManipulator $docBlockManipulator,
        NodeFactory $nodeFactory
    ) {
        $this->phpDocTagNodeFactory = $phpDocTagNodeFactory;
        $this->docBlockManipulator = $docBlockManipulator;
        $this->nodeFactory = $nodeFactory;
    }

    public function createTemporaryUuidProperty(): Property
    {
        $uuidProperty = $this->nodeFactory->createPrivateProperty('uuid');

        $this->decoratePropertyWithUuidAnnotations($uuidProperty, true, false);

        return $uuidProperty;
    }

    /**
     * Creates:
     * $this->uid = \Ramsey\Uuid\Uuid::uuid4();
     */
    public function createUuidPropertyDefaultValueAssign(string $uuidVariableName): Expression
    {
        $thisUuidPropertyFetch = new PropertyFetch(new Variable('this'), $uuidVariableName);
        $uuid4StaticCall = new StaticCall(new FullyQualified(Uuid::class), 'uuid4');

        $assign = new Assign($thisUuidPropertyFetch, $uuid4StaticCall);

        return new Expression($assign);
    }

    public function decoratePropertyWithUuidAnnotations(Property $property, bool $isNullable, bool $isId): void
    {
        $this->clearVarAndOrmAnnotations($property);
        $this->replaceIntSerializerTypeWithString($property);

        // add @var
        $this->docBlockManipulator->addTag($property, $this->phpDocTagNodeFactory->createVarTagUuidInterface());

        if ($isId) {
            // add @ORM\Id
            $this->docBlockManipulator->addTag($property, $this->phpDocTagNodeFactory->createIdTag());
        }

        $this->docBlockManipulator->addTag($property, $this->phpDocTagNodeFactory->createUuidColumnTag($isNullable));

        if ($isId) {
            $this->docBlockManipulator->addTag($property, $this->phpDocTagNodeFactory->createGeneratedValueTag());
        }
    }

    private function clearVarAndOrmAnnotations(Node $node): void
    {
        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return;
        }

        $clearedDocCommentText = Strings::replace($docComment->getText(), '#^(\s+)\*(\s+)\@(var|ORM)(.*?)$#ms');
        $node->setDocComment(new Doc($clearedDocCommentText));
    }

    /**
     * See https://github.com/ramsey/uuid-doctrine/issues/50#issuecomment-348123520.
     */
    private function replaceIntSerializerTypeWithString(Node $node): void
    {
        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return;
        }

        $stringTypeText = Strings::replace(
            $docComment->getText(),
            '#(\@Serializer\\\\Type\(")(int)("\))#',
            '$1string$3'
        );

        $node->setDocComment(new Doc($stringTypeText));
    }
}
