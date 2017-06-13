<?php

namespace MakinaCorpus\Dashboard\Tests\Datasource;

use MakinaCorpus\Dashboard\Datasource\Configuration;
use MakinaCorpus\Dashboard\Datasource\Query;
use MakinaCorpus\Dashboard\Datasource\QueryFactory;
use MakinaCorpus\Dashboard\Datasource\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the page query parsing
 */
class QueryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests basic accesors
     */
    public function testQueryBasics()
    {
        $request = new Request([
            'q'       => 'some/path/from/drupal',
            'foo'     => 'c|d|e',
            'test'    => 'test',
            'bar'     => 'baz',
            '_st'     => 'toto',
            '_by'     => 'asc',
            '_limit'  => 12,
            '_page'   => 3,
        ], [], ['_route' => 'some/path']);

        $factory = new QueryFactory();

        $configuration = new Configuration();
        $configuration->setLimitParameter('_limit');
        $configuration->disallowLimitChange();
        $query = $factory->fromRequest($configuration, $request);
        // Limit is not overridable per default
        $this->assertSame(Query::LIMIT_DEFAULT, $query->getLimit());
        // Parameters are not changed
        $this->assertFalse($query->hasSortField());
        $this->assertSame('', $query->getSortField());
        $this->assertSame(Query::SORT_DESC, $query->getSortOrder());
        $this->assertSame(1, $query->getPageNumber());
        $this->assertSame(0, $query->getOffset());

        $configuration = new Configuration();
        $configuration->setSortFieldParameter('_st');
        $configuration->setSortOrderParameter('_by');
        $configuration->setPageParameter('_page');
        $configuration->setLimitParameter('_limit');
        $configuration->allowLimitChange();
        $query = $factory->fromRequest($configuration, $request);
        // Limit is not overridable per default
        $this->assertSame(12, $query->getLimit());
        $this->assertTrue($query->hasSortField());
        $this->assertSame('toto', $query->getSortField());
        $this->assertSame(Query::SORT_ASC, $query->getSortOrder());
        // Pagination
        $this->assertSame(3, $query->getPageNumber());
        $this->assertSame(24, $query->getOffset());

        // Route, get, set
        $this->assertSame('some/path', $query->getRoute());
        $this->assertTrue($query->has('foo'));
        $this->assertFalse($query->has('non_existing'));
        $this->assertSame(['c', 'd', 'e'], $query->get('foo', 'oula'));
        $this->assertSame(27, $query->get('non_existing', 27));
    }

    /**
     * Tests behaviour with search
     */
    public function testWithSearch()
    {
        $search = 'foo:a foo:d foo:f some:other fulltext search';

        $request = new Request([
            'q'       => 'some/path',
            'foo'     => 'c|d|e',
            'test'    => 'test',
            'bar'     => 'baz',
            'search'  => $search,
        ]);

        $configuration = new Configuration();
        $configuration->setSearchParameter('search');
        $configuration->enableParseSearch();

        $factory = new QueryFactory();
        $queryFromArray = $factory->fromArray($configuration, ['foo' => ['c', 'd', 'e'], 'bar' => 'baz', 'search' => $search]);
        $queryFromRequest = $factory->fromRequest($configuration, $request);

        foreach ([$queryFromArray, $queryFromRequest] as $query) {
            $this->assertInstanceOf(Query::class, $query);

            // Test the "all" query
            $all = $query->getAll();
            $this->assertArrayNotHasKey('q', $all);
            $this->assertArrayHasKey('foo', $all);
            $this->assertArrayHasKey('some', $all);
            // Both are merged, no duplicates, outside of base query is dropped
            $this->assertCount(5, $all['foo']);
            $this->assertContains('a', $all['foo']);
            $this->assertContains('c', $all['foo']);
            $this->assertContains('d', $all['foo']);
            $this->assertContains('e', $all['foo']);
            $this->assertContains('f', $all['foo']);
            // Search only driven query is there, and flattened since there's only one element
            $this->assertSame('other', $all['some']);
            // Search is flattened
            $this->assertSame('fulltext search', $all['search']);

            // Test the "route parameters" query
            $params = $query->getRouteParameters();
            $this->assertArrayNotHasKey('q', $params);
            $this->assertArrayHasKey('foo', $params);
            $this->assertArrayNotHasKey('some', $params);
            // Route parameters are left untouched, even if it matches some base query
            // parameters, only change that may be done in that is input cleaning and
            // array expansion or flattening of values
            $this->assertCount(3, $params['foo']);
            $this->assertContains('c', $params['foo']);
            $this->assertContains('d', $params['foo']);
            $this->assertContains('e', $params['foo']);
            // Search is flattened
            $this->assertSame($search, $params['search']);
        }
    }

    /**
     * Tests behaviour with search
     */
    public function testWithBaseQuery()
    {
        $request = new Request([
            'q'       => 'some/path',
            'foo'     => 'b|c|d|e',
            'test'    => 'test',
            'bar'     => 'baz',
        ]);

        $baseQuery = ['foo' => ['a', 'b', 'c']];

        $configuration = new Configuration();
        $configuration->setSearchParameter('search');
        $configuration->enableParseSearch();

        $factory = new QueryFactory();
        $queryFromArray = $factory->fromArray($configuration, ['foo' => ['b', 'c', 'd', 'e'], 'bar' => 'baz'], $baseQuery);
        $queryFromRequest = $factory->fromRequest($configuration, $request, $baseQuery);

        foreach ([$queryFromArray, $queryFromRequest] as $query) {
            $this->assertInstanceOf(Query::class, $query);

            // Test the "all" query
            $all = $query->getAll();
            // Only those from base query are allowed, and those which are
            // not explicitely added to parameter are removed
            // i.e. base query is [a, b] and current query is [b, c] then
            // only b is visible (asked by query), a is dropped (not in query)
            // and c is dropped (not ine base query)
            $this->assertCount(2, $all['foo']);
            $this->assertNotContains('a', $all['foo']);
            $this->assertContains('b', $all['foo']);
            $this->assertContains('c', $all['foo']);
            $this->assertNotContains('d', $all['foo']);
            $this->assertNotContains('e', $all['foo']);

            // Test the "route parameters" query
            $params = $query->getRouteParameters();
            $this->assertArrayNotHasKey('q', $params);
            $this->assertArrayHasKey('foo', $params);
            $this->assertArrayNotHasKey('some', $params);
            // Route parameters are subject to base query change too
            $this->assertCount(2, $params['foo']);
            $this->assertContains('b', $params['foo']);
            $this->assertContains('c', $params['foo']);
            $this->assertNotContains('d', $params['foo']);
            $this->assertNotContains('e', $params['foo']);

            $this->assertSame($baseQuery, $query->getBaseQuery());
        }
    }

    /**
     * Tests behaviour without search
     */
    public function testWithoutSearch()
    {
            $search = 'foo:a foo:d foo:f some:other fulltext search';

        $request = new Request([
            'q'       => 'some/path',
            'foo'     => 'c|d|e',
            'test'    => 'test',
            'bar'     => 'baz',
            'search'  => $search,
        ]);

        $configuration = new Configuration();
        $configuration->setSearchParameter('search');
        $configuration->disableParseSearch();

        $factory = new QueryFactory();
        $queryFromArray = $factory->fromArray($configuration, ['foo' => ['c', 'd', 'e'], 'bar' => 'baz', 'search' => $search]);
        $queryFromRequest = $factory->fromRequest($configuration, $request);

        foreach ([$queryFromArray, $queryFromRequest] as $query) {
            $this->assertInstanceOf(Query::class, $query);

            // Test the "all" query
            $all = $query->getAll();
            $this->assertArrayNotHasKey('q', $all);
            $this->assertArrayNotHasKey('some', $all);
            $this->assertArrayNotHasKey('other', $all);
            $this->assertArrayHasKey('foo', $all);
            // Both are merged, no duplicates, outside of base query is dropped
            $this->assertCount(3, $all['foo']);
            $this->assertNotContains('a', $all['foo']);
            $this->assertNotContains('f', $all['foo']);
            $this->assertContains('c', $all['foo']);
            $this->assertContains('d', $all['foo']);
            $this->assertContains('e', $all['foo']);
            // 'f' is only visible in parsed search, drop it
            $this->assertNotContains('f', $all['foo']);
            $this->assertSame($search, $all['search']);

            // Test the "route parameters" query
            $params = $query->getRouteParameters();
            $this->assertArrayNotHasKey('q', $params);
            $this->assertArrayHasKey('foo', $params);
            $this->assertArrayNotHasKey('some', $params);
            // Route parameters are left untouched, even if it matches some base query
            // parameters, only change that may be done in that is input cleaning and
            // array expansion or flattening of values
            $this->assertCount(3, $params['foo']);
            $this->assertContains('c', $params['foo']);
            $this->assertContains('d', $params['foo']);
            $this->assertContains('e', $params['foo']);
            // Search is flattened
            $this->assertSame($search, $params['search']);
        }
    }

    /**
     * Tests query string parser
     */
    public function testQueryStringParser()
    {
        $queryString = 'field1:13 foo:"bar baz" bar:2 innner:"this one has:inside" full text bar:test bar:bar not:""';

        $parsed = (new QueryStringParser())->parse($queryString, 's');

        $this->assertCount(1, $parsed['field1']);
        $this->assertSame('13', $parsed['field1'][0]);

        $this->assertCount(1, $parsed['foo']);
        $this->assertSame('bar baz', $parsed['foo'][0]);

        $this->assertCount(3, $parsed['bar']);
        $this->assertSame('2', $parsed['bar'][0]);
        $this->assertSame('test', $parsed['bar'][1]);
        $this->assertSame('bar', $parsed['bar'][2]);

        $this->assertArrayNotHasKey('has', $parsed);
        $this->assertCount(1, $parsed['innner']);
        $this->assertSame('this one has:inside', $parsed['innner'][0]);

        $this->assertArrayNotHasKey('not', $parsed);

        $this->assertCount(2, $parsed['s']);
        $this->assertSame('full', $parsed['s'][0]);
        $this->assertSame('text', $parsed['s'][1]);
    }
}