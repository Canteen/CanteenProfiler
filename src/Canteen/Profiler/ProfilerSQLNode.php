<?php

/**
*  @module Canteen\Profiler
*/
namespace Canteen\Profiler
{	
	class ProfilerSQLNode
	{
		/**
		*  The query that this object tracks
		*  @property {String} query
		*  @protected
		*/
		protected $query;

		/**
		*  Reference to the step this SQL query runs in
		*  @property {ProfilerNode} profileNode
		*  @protected
		*/
		protected $profileNode;

		/**
		*  Start time of this query (in microseconds)
		*  @property {Number} started
		*  @protected
		*/
		protected $started = null;

		/**
		*  End time of this query (in microseconds)
		*  @property {Number} ended
		*  @protected
		*/
		protected $ended = null;

		/**
		*  Duration for this query (in microseconds)
		*  @property {Number} duration
		*  @protected
		*/
		protected $duration = null;

		/**
		*  Call stack backtrace of all methods/functions executed up until this SQL query is run
		*  @property {Array} callstack
		*  @protected
		*/
		protected $callstack = [];

		/**
		*  Class representing each SQL query run
		*  @class ProfilerSQLNode
		*  @extends CanteenBase
		*  @constructor
		*  @param {String} query the sql query for this node
		*  @param {Boolean|ProfilerNode} [profileNode=null] reference to the step 
		*  that this query is running within
		*/
		public function __construct($query, $profileNode = null)
		{
			$this->started = microtime(true);
			$this->query = $query;
			$this->profileNode = $profileNode;

			$this->callstack = debug_backtrace();
			array_shift($this->callstack);
			array_shift($this->callstack);
		}

		/**
		*  End the timers for this sql node. Call this method when 
		*  the sql query has finished running.
		*  @method end
		*  @return {ProfilerSQLNode} return a reference to this query, for chaining.
		*/
		public function end()
		{
			if (null == $this->ended)
			{
				$this->ended = microtime(true);
				$this->duration = $this->ended - $this->started;
				$this->profileNode->addQueryDuration($this->duration);
				$this->profiler->addQueryDuration($this->duration);
			}
			return $this;
		}

		/**
		*  Get the query for this SQLNode. Query is parsed so extraneous 
		*  spaces are removed where required.
		*  @method getQuery
		*  @return {String} Query for this node
		*/
		public function getQuery()
		{
			return preg_replace('#^\s+#m', "\n", $this->query);
		}

		/**
		*  Get the type of query this is. Parse the query and try to figure out 
		*  what kind of query it is.
		*  @method getQueryType
		*  @return {String} 'reader' if this is a select query, 'writer' if this is a typical writer query, or 'special' if it's another kind
		*/
		public function getQueryType()
		{
			list($start_clause) = preg_split("#\s+#", $this->getQuery()); 

			$start_clause = strtolower($start_clause);

			switch ($start_clause)
			{
				case 'select':
					$type = 'reader';
				break;

				case 'insert':
				case 'update':
				case 'delete':
					$type = 'writer';
				break;

				default:
					$type = 'special';
				break;
			}

			return $type;
		}

		/**
		*  Get the total execution duration for this query
		*  @method getDuration
		*  @return {Number} Execution duration for this query in milliseconds, rounded to 1 significant digit.
		*/
		public function getDuration()
		{
			return round($this->duration*  1000, 1);
		}

		/**
		*  Get the start time of this query, from the unix epoch.
		*  @method getStart
		*  @return {Number} milliseconds from the unix epoch when this query started, rounded to 1 significant digit
		*/
		public function getStart()
		{
			return round($this->started*  1000, 1);
		}

		/**
		*  Return the call stack for this query. Reference the php documentation 
		*  for {@link http://php.net/debug_backtrace debug_backtrace} for the 
		*  structure of the return array.
		*  @method getCallstack
		*  @return {Array} call stack for this query
		*/
		public function getCallstack()
		{
			return $this->callstack;
		}
	}
}