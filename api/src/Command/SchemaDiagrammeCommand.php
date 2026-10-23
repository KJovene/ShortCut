<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\ORM\Mapping\OneToOneOwningSideMapping;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Dessine le schéma de la base à partir du mapping Doctrine, en diagramme Mermaid (affiché par GitHub et VS Code).
 */
#[AsCommand(name: 'app:schema:diagramme', description: 'Génère le diagramme entité-relation de la base (Mermaid) depuis le mapping Doctrine')]
final class SchemaDiagrammeCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('markdown', null, InputOption::VALUE_NONE, 'Entoure le diagramme d\'une page Markdown (pour docs/schema.md)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $metadonnees = $this->em->getMetadataFactory()->getAllMetadata();
        usort($metadonnees, fn (ClassMetadata $a, ClassMetadata $b): int => $a->getTableName() <=> $b->getTableName());

        $tables = [];
        $relations = [];
        foreach ($metadonnees as $meta) {
            $table = strtoupper($meta->getTableName());
            $lignes = [];
            foreach ($meta->fieldMappings as $champ) {
                $cle = $meta->isIdentifier($champ->fieldName) ? ' PK' : ($champ->unique ? ' UK' : '');
                $notes = array_filter([
                    null !== $champ->enumType ? 'enum '.substr((string) strrchr($champ->enumType, '\\'), 1) : null,
                    $champ->nullable ? 'nullable' : null,
                ]);
                $lignes[] = \sprintf('        %s %s%s%s', $champ->type, $champ->columnName, $cle, [] !== $notes ? ' "'.implode(', ', $notes).'"' : '');
            }

            foreach ($meta->associationMappings as $association) {
                if (!$association instanceof ManyToOneAssociationMapping && !$association instanceof OneToOneOwningSideMapping) {
                    continue;
                }
                $jointure = $association->joinColumns[0];
                $nullable = $jointure->nullable ?? true;
                $lignes[] = \sprintf('        integer %s FK%s', $jointure->name, $nullable ? ' "nullable"' : '');

                $cible = strtoupper($this->em->getClassMetadata($association->targetEntity)->getTableName());
                $gauche = $nullable ? '|o' : '||';
                $droite = $association instanceof ManyToOneAssociationMapping ? 'o{' : 'o|';
                $relations[] = \sprintf('    %s %s--%s %s : %s', $cible, $gauche, $droite, $table, $association->fieldName);
            }

            $tables[] = \sprintf("    %s {\n%s\n    }", $table, implode("\n", $lignes));
        }

        $mermaid = "erDiagram\n".implode("\n", $tables)."\n".implode("\n", $relations)."\n";

        if ($input->getOption('markdown')) {
            $mermaid = \sprintf(
                "# Schéma de la base\n\nGénéré depuis le mapping Doctrine par `make schema` : ne pas modifier à la main.\n\n```mermaid\n%s```\n",
                $mermaid,
            );
        }

        $output->write($mermaid);

        return Command::SUCCESS;
    }
}
