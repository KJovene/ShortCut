<?php

namespace App\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * MOIS_COUVERT(debut, fin, :mois) en DQL : 1 si la période touche ce mois (1 à 12), quelle que soit l'année,
 * sinon 0. Le climat est une moyenne : « mai » doit trouver une période saisie pour mai 2027 comme pour mai 2028.
 * Usage : `MOIS_COUVERT(p.debut, p.fin, :mois) = 1`.
 */
final class MoisCouvert extends FunctionNode
{
    private Node $debut;
    private Node $fin;
    private Node $mois;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->debut = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->fin = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->mois = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        $debut = $this->debut->dispatch($sqlWalker);
        $fin = $this->fin->dispatch($sqlWalker);
        // Le mois n'est écrit qu'une fois (sous-requête) : un paramètre répété dans le SQL ne serait lié qu'une fois.
        $mois = 'CAST('.$this->mois->dispatch($sqlWalker).' AS integer)';

        // Sur deux années civiles ou plus : tous les mois. Dans la même année : entre le mois de début et celui de fin.
        // À cheval sur deux années (ex. novembre à février) : à partir du mois de début ou jusqu'au mois de fin.
        return \sprintf(
            '(SELECT CASE WHEN EXTRACT(YEAR FROM %2$s) - EXTRACT(YEAR FROM %1$s) >= 2 THEN 1'
            .' WHEN EXTRACT(YEAR FROM %2$s) = EXTRACT(YEAR FROM %1$s)'
            .' THEN (CASE WHEN m.mois BETWEEN EXTRACT(MONTH FROM %1$s) AND EXTRACT(MONTH FROM %2$s) THEN 1 ELSE 0 END)'
            .' WHEN m.mois >= EXTRACT(MONTH FROM %1$s) OR m.mois <= EXTRACT(MONTH FROM %2$s) THEN 1 ELSE 0 END'
            .' FROM (SELECT %3$s AS mois) m)',
            $debut, $fin, $mois,
        );
    }
}
