<?php

namespace App\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * JSON_CONTIENT(colonne, :json) en DQL : vrai si la liste JSON de la colonne contient toutes les valeurs
 * de :json (opérateur PostgreSQL @>). Les listes comme `envies` sont stockées en JSON, que le DQL ne sait pas lire.
 * Usage : `JSON_CONTIENT(p.envies, :envie) = true` avec `:envie` = `["plage"]`.
 */
final class JsonContient extends FunctionNode
{
    private Node $colonne;
    private Node $valeur;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->colonne = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->valeur = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return \sprintf(
            'CAST(%s AS jsonb) @> CAST(%s AS jsonb)',
            $this->colonne->dispatch($sqlWalker),
            $this->valeur->dispatch($sqlWalker),
        );
    }
}
