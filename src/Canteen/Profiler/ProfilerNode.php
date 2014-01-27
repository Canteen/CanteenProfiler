<?php

/**
*  @module Canteen\Profiler
*/
namespace Canteen\Profiler
{	
	class ProfilerNode
	{
		/**
		*  Name of the step
		*  @property {String} name
		*  @protected
		*/
		protected $name;

		/**
		*  Tree depth of this step
		*  @property {int} depth
		*  @protected
		*/
		protected $depth = 0;

		/**
		*  Time the step started. Stored as microseconds from the unix epoc.
		*  @property {Number} started
		*  @protected
		*/
		protected $started = null;

		/**
		*  Time the step ended. Stored as microseconds from the unix epoc.
		*  @property {Number} ended
		*  @protected
		*/
		protected $ended = null;

		/**
		*  Total time the step ran INCLUDING it's children
		*  @property {Number} totalDuration
		*  @protected
		*/
		protected $totalDuration = null;

		/**
		*  Total time the step ran WITHOUT it's children
		*  @property {Number} selfDuration
		*  @protected
		*/
		protected $selfDuration = null;

		/**
		*  Total time children steps spent running
		*  @property {Number} childDuration
		*  @protected
		*/
		protected $childDuration = 0;

		/**
		*  The parent step to this node
		*  @property {ProfilerNode} parentNode
		*  @protected
		*/
		protected $parentNode = null;

		/**
		*  List of this step's direct children
		*  @property {Array} childNodes
		*  @protected
		*/
		protected $childNodes = [];

		/**
		*  Number of queries run at this step
		*  @property {int} sqlQueryCount
		*  @protected
		*/
		protected $sqlQueryCount = 0;

		/**
		*  List of this step's SQL queries
		*  @property {Array} sqlQueries
		*  @protected
		*/
		protected $sqlQueries = [];

		/**
		*  Total time spent performing SQL queries. Stored in microseconds
		*  @property {Number} totalSQLQueryDuration
		*  @protected
		*/
		protected $totalSQLQueryDuration = 0;

		/**
		*  Local reference to profiler key generated at initialization
		*  @property {String} profilerKey
		*  @protected
		*/
		protected $profilerKey = null;

		/**
		*  Class which represents the profiler steps
		*  
		*  @class ProfilerNode
		*  @constructor
		*  @param {string} name Name of this step
		*  @param {int} depth Tree depth of this step
		*  @param {ProfilerNode} parentNode Reference to this step's parent. null if top-level.
		*  @param {string} profilerKey API key to identify an internal API call from an external one.
		*/
		public function __construct($name, $depth, $parentNode, $profilerKey)
		{
			$this->started = microtime(true);
			$this->name = $name;
			$this->depth = $depth;
			$this->parentNode = $parentNode;
			$this->profilerKey = $profilerKey;
		}

		/**
		*  End the timer for this step. Call this after the code that is being 
		*  profiled by this step has completed executing
		*  @method end
		*  @param {String} [profilerKey=null] This is for internal use only! don't ever pass anything!
		*  @return {Boolean|ProfilerNode} returns parent node, or null if there is no parent
		*/
		public function end($profilerKey = null)
		{
			if (!$profilerKey || $profilerKey != $this->profilerKey)
			{
				$this->profiler->end($this->name);

				return $this->parentNode;
			}

			if (null == $this->ended)
			{
				$this->ended = microtime(true);
				$this->totalDuration = $this->ended - $this->started;
				$this->selfDuration = $this->totalDuration - $this->childDuration;

				if ($this->parentNode)
				{
					$this->parentNode->increaseChildDuration($this->totalDuration);
					$this->profiler->addDuration( $this->selfDuration );
				}
			}

			return $this->parentNode;
		}

		/**
		*  This method is called by the Profiler::sqlStart method
		*  @method sqlStart
		*  @param {ProfilerSQLNode} sqlProfile An instance of the {@link ProfilerSQLNode} to add to this step
		*  @return {ProfilerSQLNode} reference to the {@link ProfilerSQLNode} object for the query initiated
		*/
		public function sqlStart(ProfilerSQLNode $sqlProfile)
		{
			$this->sqlQueries []= $sqlProfile;
			$this->sqlQueryCount ++;

			return $sqlProfile;
		}

		/**
		*  Return the name of this step
		*  @method getName
		*  @return {String} Name of this step
		*/
		public function getName()
		{
			return $this->name;
		}

		/**
		*  Return tree depth of this step
		*  @method getDepth
		*  @return {int} Tree depth of this step
		*/
		public function getDepth()
		{
			return $this->depth;
		}

		/**
		*  Return this step's parent node
		*  @method getParent
		*  @return {Boolean|ProfilerNode} Returns {@link ProfilerNode} object for the parent node to this step, or null if there is no parent
		*/
		public function getParent()
		{
			return $this->parentNode;
		}

		/**
		 * Increase the total time child steps have taken. Stored in microseconds
		 * @method increaseChildDuration
		 * @param {Number} time Amount of time to add to the total child duration, in microseconds
		 * @return {Number} Return number total time child steps have taken, in microseconds
		 */
		public function increaseChildDuration($time)
		{
			$this->childDuration += $time;

			return $this->childDuration;
		}

		/**
		 *  Add child {@link ProfilerNode} to this node
		 *  @method addChild
		 *  @param {ProfilerNode} childNode the profiler node to add
		 *  @return {ProfilerNode} Return a reference to this profiler node (for chaining)
		 */
		public function addChild(ProfilerNode $childNode)
		{
			$this->childNodes []= $childNode;
			return $this;
		}

		/**
		*  Determine if this node has child steps or not
		*  @method hasChildren
		*  @return {Boolean} True if this node has child steps, false otherwise
		*/
		public function hasChildren()
		{
			return count($this->childNodes) > 0? true : false;
		}

		/**
		*  Get the children steps for this step
		*  @method getChildren
		*  @return {Array} List of {@link ProfilerNodes} that are the child of this node
		*/
		public function getChildren()
		{
			return $this->childNodes;
		}

		/**
		*  Determine if this node has trivial children. Traverse the tree of child 
		*  steps until a non-trivial node is found.  This is used at render time.
		*  @method hasNonTrivialChildren
		*  @return {Boolean} False if all children are trivial, true if there's at least one non-trivial
		*/
		public function hasNonTrivialChildren()
		{
			if ($this->hasChildren())
			{
				foreach ($this->getChildren() as $child)
				{
					if (!$this->profiler->isTrivial($child))
					{
						return true;
					}
					if ($child->hasNonTrivialChildren())
					{
						return true;
					}
				}
			}

			return false;
		}

		/**
		*  Determine if SQL queries were executed at this step
		*  @method hasSQLQueries
		*  @return {Boolean} True if there are queries, false if not
		*/
		public function hasSQLQueries()
		{
			return $this->sqlQueryCount > 0? true : false;
		}

		/**
		*  Get all the SQL queries executed at this step
		*  @method getSQLQueries
		*  @return {Array} list of {@link ProfilerSQLNode}s
		*/
		public function getSQLQueries()
		{
			return $this->sqlQueries;
		}

		/**
		*  Return number of queries run at this step
		*  @method getSQLQueryCount
		*  @return {int} number of queries run at this step
		*/
		public function getSQLQueryCount()
		{
			return $this->sqlQueryCount;
		}

		/**
		*  Increment the total sql duration at this step 
		*  @method addQueryDuration
		*  @param {Number} time amount of time to increment the SQL duration by, in microseconds
		*  @return {ProfilerNode} Return instance of this step, for chaining
		*/
		public function addQueryDuration($time)
		{
			$this->totalSQLQueryDuration += $time;
		}	

		/**
		*  Get the total duration for SQL queries executed at this step in milliseconds
		*  @method getTotalSQLQueryDuration
		*  @return {Number} Duration of query time at this step, in milliseconds, 1 significant digit
		*/
		public function getTotalSQLQueryDuration()
		{
			return round($this->totalSQLQueryDuration * 1000, 1);
		}

		/**
		*  Get the start time of this step in milliseconds
		*  @method getStart
		*  @return {Number} Start time of this step, in milliseconds, from unix epoch. (1 significant digit)
		*/
		public function getStart()
		{
			return round($this->started * 1000, 1);
		}

		/**
		*  Get the end time of this step in milliseconds
		*  @method getEnd
		*  @return {Number} End time of this step, in milliseconds, from unix epoch. (1 significant digit)
		*/
		public function getEnd()
		{
			return round($this->ended * 1000, 1);
		}

		/**
		*  Get the total time spent executing this node, including children
		*  @method getTotalDuration
		*  @return {Number} Duration of this step, in milliseconds. (1 significant digit)
		*/
		public function getTotalDuration()
		{
			return round($this->totalDuration * 1000, 1);
		}

		/**
		*  Get the duration of execution for this step, excluding child nodes.
		*  @method getSelfDuration
		*  @return {Number} Duration of this step, excluding child nodes. (1 significant digit)
		*/
		public function getSelfDuration()
		{
			return round($this->selfDuration * 1000, 1);
		}
	}
}