<?php

/**
*  @module Canteen\Profiler
*/
namespace Canteen\Profiler
{	
	class ProfilerRenderer
	{
		/**
		*  The rendered normal nodes 
		*  @property {String} nodes
		*/
		public $nodes = '';
		
		/**
		*  The rendered query nodes 
		*  @property {String} queryNodes
		*/
		public $queryNodes = '';

		/**
		*  Instance of the Profiler
		*  @property {Profiler} _profiler
		*  @private
		*/
		private $_profiler;
		
		/**
		*  Rendering class used to render special step nodes.
		*  @class ProfilerRenderer
		*  @extends CanteenBase
		*  @constructor
		*  @param {Array} topNodes The collection of ProfilerNodes to render
		*  @param {Number} showDepth The depth amount to show
		*/
		public function __construct(Profiler $profiler, $topNodes, $showDepth)
		{
			$this->_profiler = $profiler;

			foreach($topNodes as $node)
			{
				$this->nodes .= $this->renderNode($node, $showDepth);
				$this->queryNodes .= $this->renderNodeSQL($node);
			}
		}
		
		/**
		*   Destroy
		*/
		public function __destruct()
		{
			$this->nodes = null;
			$this->queryNodes = null;
		}
		
		/**
		*  Render a {@link ProfilerNode} step node and it's children recursively
		*  @method renderNode
		*  @private
		*  @param {ProfilerNode} node The node to render
		*  @param {int} [maxDepth=-1] the maximum depth of the tree to traverse and render.  -1 to traverse entire tree
		*  @return {String} The HTML markup rendering of the profiler node
		*/
		private function renderNode($node, $maxDepth = -1) 
		{ 
			$res = $this->_profiler->parser->template(
				'ProfilerNode',
				[
					'depth' => $node->getDepth(),
					'trivial' => $this->_profiler->isTrivial($node) && !$node->hasNonTrivialChildren() ? 'profiler-trivial' : '',
					'indent' => str_repeat('&nbsp;&nbsp;&nbsp;', $node->getDepth() - 1),
					'name' => $node->getName(),
					'selfDuration' => $node->getSelfDuration(),
					'totalDuration' => $node->getTotalDuration(),
					'startDelay' => round($node->getStart() - $this->_profiler->getGlobalStart(), 1),
					'id' => md5($node->getName() . $node->getStart()),
					'queryCount' => $node->getSQLQueryCount(),
					'queryTime' => $node->getTotalSQLQueryDuration()
				]
			);

			if ($node->hasChildren() && ($maxDepth == -1 || $maxDepth > $node->getDepth()))
			{
				foreach ($node->getChildren() as $childNode)
				{
					$res .= $this->renderNode($childNode, $maxDepth);
				}
			}
			return $res;
		}

		/**
		*  Render all {@link ProfilerSQLNode} queries for the given node, and traverse it's child nodes
		*  to render their queries also.
		*  @method renderNodeSQL
		*  @private
		*  @param {ProfilerNode} node The node to begin rendering
		*  @return {String} The HTML markup rendering of the SQL node
		*/
		private function renderNodeSQL($node)
		{
			$res = '';
			
			if ($node->hasSQLQueries())
			{
				$c = 0; //row counter
				$nodeQueries = $node->getSQLQueries();

				$id = md5($node->getName() . $node->getStart());

				$res .= $this->_profiler->parser->template(
					'ProfilerSQLHeader', 
					[
						'id' => $id,
						'name' => $node->getName()
					]
				);

				foreach ($nodeQueries as $query)
				{
					$stack = [];
					foreach ($query->getCallstack() as $stackStep)
					{
						$stack[] = [
							'rowClass' => ++$c % 2? 'odd' : 'even',
							'class' => !empty($stackStep['class'])? $stackStep['class'] . $stackStep['type'] : '',
							'function' => $stackStep['function']
						];
					}
					$res .= $this->_profiler->parser->template(
						'ProfilerSQLNode', 
						[
							'id' => $id,
							'startTimer' => round($query->getStart() - $this->_profiler->getGlobalStart(), 1),
							'duration' => $query->getDuration(),
							'type' => $query->getQueryType(),
							'stack' => $stack,
							'queryId' => md5($query->getQuery()),
							'query' => $query->getQuery()
						]
					);
				}
			}

			if ($node->hasChildren())
			{
				foreach ($node->getChildren() as $childNode)
				{
					$res .= $this->renderNodeSQL($childNode);
				}
			}
			return $res;
		}
	}
}