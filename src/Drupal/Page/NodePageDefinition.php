<?php

namespace MakinaCorpus\Dashboard\Drupal\Page;

use MakinaCorpus\Dashboard\Datasource\DatasourceInterface;
use MakinaCorpus\Dashboard\Datasource\InputDefinition;
use MakinaCorpus\Dashboard\DependencyInjection\AbstractPageDefinition;
use MakinaCorpus\Dashboard\View\Html\TwigView;
use MakinaCorpus\Dashboard\View\ViewDefinition;

/**
 * Default node admin page implementation, suitable for most use cases
 */
class NodePageDefinition extends AbstractPageDefinition
{
    private $datasource;
    private $queryFilter;
    private $permission;

    /**
     * Default constructor
     *
     * @param DatasourceInterface $datasource
     * @param mixed[] $queryFilter
     */
    public function __construct(DatasourceInterface $datasource, array $queryFilter = [])
    {
        $this->datasource = $datasource;
        $this->queryFilter = $queryFilter;
    }

    /**
     * Get default query filters
     *
     * @return array
     */
    final protected function getQueryFilters()
    {
        return $this->queryFilter ? $this->queryFilter : [];
    }

    /**
     * {@inheritdoc}
     */
    public function getInputDefinition (array $options = [])
    {
        return new InputDefinition(
            $this->datasource, [
                'base_query' => $this->getQueryFilters(),
                'search_parse' => true,
                'search_enable' => true,
            ] + $options
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getViewDefinition()
    {
        return new ViewDefinition([
            'default_display' => 'table',
            'show_search' => true,
            'templates' => [
                'grid' => 'module:udashboard:views/Page/page-grid.html.twig',
                'table' => 'module:udashboard:views/Page/page.html.twig',
            ],
            'view_type' => TwigView::class,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getDatasource()
    {
        return $this->datasource;
    }
}