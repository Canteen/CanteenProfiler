<?php

/**
*  Original library from:
*  @link http://github.com/jimrubenstein/php-profiler
*  @author Jim Rubenstein <jrubenstein@gmail.com>
*/

/**
*  @module Canteen\Profiler
*/
namespace Canteen\Profiler
{
	use \Exception;
	use Canteen\Parser\Parser;
	
	/**
	*  The Profiler is used to analyze your application in order to determine where you could use
	*  the most optimization. The profiler class is where all interaction with the Profiler takes 
	*  place. You use it to create step nodes and render the output.
	*  @class Profiler
	*  @constructor
	*  @param {Parser} [parser=null] Optional to pass in existing Parser
	*/
	class Profiler
	{		
		/**
		*  Used to insure that the {@link init} method is only called once.
		*  @property {Boolean} init
		*  @protected
		*/
		protected $init = false;

		/**
		*  Used to identify when the profiler has been enabled. If <em>false</em> no 
		*  profiling data is stored, in order to reduce the overhead of running the profiler
		*  @property {Boolean} enabled
		*  @protected
		*/
		protected $enabled = false;

		/**
		*  Tracks the current step node.
		*  @property {ProfilerNode} currentNode
		*  @protected
		*/
		protected $currentNode = null;

		/**
		*  Tracks the current SQL note
		*  @property {ProfilerSQLNode} sqlProfile
		*  @protected
		*/
		protected $sqlProfile = null;

		/**
		*  Tracks the current tree depth
		*  @property {int} depthCount
		*  @protected
		*/
		protected $depthCount = 0;

		/**
		*  List of all top-level step nodes
		*  @property {Array} topNodes
		*  @protected
		*/	
		protected $topNodes = [];

		/**
		*  Time the profiler was included. This is used to calculate 
		*  time-from-start values for all methods as well as total running time.
		*  @property {Number} globalStart
		*  @protected
		*/	
		protected $globalStart = 0;

		/**
		*  Time the profiler 'ends'. This is populated just before rendering 
		*  output (see {@link Profiler::render()})
		*  @property {Number} globalEnd
		*  @protected
		*/
		protected $globalEnd = 0;

		/**
		*  Total time script took to run
		*  @property {Number} globalDuration
		*  @protected
		*/
		protected $globalDuration = 0;

		/**
		*  Global tracker for step times. Keeps track of how long each node 
		*  took to execute.  This is used to determine
		*  what is a "trivial" node, and what is not.
		*  @property {Array} childDurations
		*  @protected  
		*/	
		protected $childDurations = [];

		/**
		*  Percentile boundary for trivial execution times
		*  @property {Number} trivialThreshold
		*  @protected
		*/	
		protected $trivialThreshold = .75;

		/**
		*  Execution time cut off value for trivial/non-trivial nodes
		*  @property {Number} trivialThresholdMS
		*  @protected
		*/
		protected $trivialThresholdMS = 0;

		/**
		*  Total amount of time used in SQL queries
		*  @property {Number} totalQueryTime
		*  @protected
		*/
		protected $totalQueryTime = 0;

		/**
		*  Used to identify when some methods are accessed internally
		*  versus when they're used externally (as an api or so)
		*  @property {String} profilerKey
		*  @protected
		*/	
		protected $profilerKey = null;

		/**
		*  A lightweight shell node used to return when the profiler is disabled.
		*  @property {ProfilerGhostNode} ghostNode
		*  @protected
		*/	
		protected $ghostNode;

		/**
		*  The parser instance
		*  @property {Parser} parser
		*  @readOnly
		*/
		private $_parser;

		/**
		*  Create a constructor that basically says "don't construct me!"
		*/
		public function __construct($parser=null)
		{
			if ($this->init) return;
			
			$this->globalStart = microtime(true);
			$this->profilerKey = md5(rand(1,1000) . 'louddoor!' . time());
			$this->ghostNode = new ProfilerGhostNode;
			$this->enabled = true;
			$this->init = true;

			//  The parser
			$this->_parser = $parser instanceof Parser ? $parser : new Parser();

			// The list of templates
			$templates = [
				'Profiler', 
				'ProfilerNode', 
				'ProfilerSQLHeader',
				'ProfilerSQLNode',
				'ProfilerScript',
				'ProfilerStyles'
			];

			// Add the templates
			foreach($templates as $t)
			{
				$this->_parser->addTemplate($t, __DIR__.'/Templates/'.$t.'.html');
			}
		}

		/**
		*  Getter for the readOnly properties
		*/
		public function __get($name)
		{
			$default = '_'.$name;

			if (property_exists($this, $default))
			{
				return $this->$default;
			}
		}

		/**
		*  Check to see if the profiler is enabled 
		*  @method isEnabled
		*  @return {Boolean} True if profiler is enabled, false if disabled
		*/
		public function isEnabled()
		{
			return $this->enabled;
		}

		/**
		*  Enable the profiler
		*  @method enable
		*/
		public function enable()
		{
			$this->enabled = true;
		}

		/**
		*  Disable the profiler
		*  @method disable
		*/
		public function disable()
		{
			if ($this->currentNode == null && count($this->topNodes) == 0)
			{
				$this->enabled = false;
			}
			else
			{
				throw new exception("Can not disable profiling once it has begun.");
			}
		}

		/**
		*  Start a new step. This is the most-called method of the profiler.  
		*  It initializes and returns a new step node.
		*  @method start
		*  @param {Sstring} nodeName name/identifier for your step. is 
		*   used later in the output to identify this step
		*  @return {ProfilerNode|ProfilerGhostNode} returns an instance of 
		*  	a {@link ProfilerNode} if the profiler is enabled, or 
		*   a {@link ProfilerGhostNode} if it's disabled
		*/	
		public function start($nodeName)
		{	
			if (!$this->isEnabled()) return $this->ghostNode;

			$newNode = new ProfilerNode($nodeName, ++$this->depthCount, $this->currentNode, $this->profilerKey);

			if ($this->currentNode)
			{
				$this->currentNode->addChild($newNode);
			}
			else
			{
				$this->topNodes []= $newNode;
			}

			$this->currentNode = $newNode;

			return $this->currentNode;
		}

		/**
		*  End a step by name, or end all steps in the current tree.
		*  @method end
		*  @param {String} nodeName ends the first-found step with this name. (Note: a warning is generated if it's not the current step, because this is probably unintentional!)
		*  @param {Boolean} nuke denotes whether you are intentionally attempting to terminate the entire step-stack.  If true, the warning mentioned is not generated.
		*  @return {Boolean}|ProfilerNode|ProfilerGhostNode returns null if you ended the top-level step node, or the parent to the ended node, or a ghost node if the profiler is disabled.
		*/
		public function end($nodeName, $nuke = false)
		{	
			if (!$this->isEnabled()) return $this->ghostNode;

			if ($this->currentNode == null)
			{
				return;
			}

			while ($this->currentNode && $this->currentNode->getName() != $nodeName)
			{
				if (!$nuke)
				{
					trigger_error("Ending profile node '" . $this->currentNode->getName() . "' out of order (Requested end: '{$nodeName}')", E_USER_WARNING);
				}

				$this->currentNode = $this->currentNode->end($this->profilerKey);
				$this->depthCount --;
			}

			if ($this->currentNode && $this->currentNode->getName() == $nodeName)
			{
				$this->currentNode = $this->currentNode->end($this->profilerKey);
				$this->depthCount --;
			}

			return $this->currentNode;
		}

		/**
		*  Start a new sql query
		*
		*  This method is used to tell the profiler to track an sql query.  These are treated differently than step nodes
		*  @method sqlStart
		*  @param {String} query the query that you are running (used in the output of the profiler so you can view the query run)
		*  @return {ProfilerSQLNode|ProfilerGhostNode} returns an instance of the {@link ProfilerGhostNode} if profiler is enabled, or {@link ProfilerGhostNode} if disabled
		*/
		public function sqlStart($query)
		{	
			if (!$this->isEnabled()) return $this->ghostNode;

			if (!$this->currentNode)
			{
				$this->start("Profiler Default Top Level");			
			}

			$this->sqlProfile = new ProfilerSQLNode($query, $this->currentNode);

			$this->currentNode->sqlStart($this->sqlProfile);

			return $this->sqlProfile;
		}

		/**
		*  Stop profiling the current SQL call
		*  @method sqlEnd
		*/
		public function sqlEnd()
		{
			if (!$this->sqlProfile) return;

			$this->sqlProfile->end();
		}

		/**
		*  Increment the total query time
		*
		*  This method is used by the {@link ProfilerGhostNode} to increment the total query time for the page execution.
		*  This method should <b>never</b> be called in userland.  There is zero need to.
		*  @method addQueryDuration
		*  @param {Number} time amount of time the query took to execute in microseconds.
		*  @return {Number} Current amount of time (in microseconds) used to execute sql queries.
		*/
		public function addQueryDuration($time)
		{
			return $this->totalQueryTime += $time;
		}

		/**
		*  Get the total amount of query time
		*  @method getTotalQueryTime
		*  @return {Number} Total time used to execute sql queries (milliseconds, 1 significant digit)
		*/
		public function getTotalQueryTime()
		{
			return round($this->totalQueryTime*  1000, 1);
		}

		/**
		*  Get the global start time
		*  @method getGlobalStart
		*  @return {Number} Start time of the script from unix epoch (milliseconds, 1 significant digit)
		*/
		public function getGlobalStart()
		{
			return round($this->globalStart*  1000, 1);
		}

		/**
		*  Get the global script duration
		*  @method getGlobalDuration
		*  @return {Number} Duration of the script (in milliseconds, 1 significant digit)
		*/
		public function getGlobalDuration()
		{
			return round($this->globalDuration*  1000, 1);
		}

		/**
		*  Get the global memory usage in KB
		*  @method getMemUsage
		*  @param {String} [unit=''] a metric prefix to force the unit of bytes used (B, K, M, G)
		*
		*/
		public function getMemUsage($unit = '')
		{
			$usage = memory_get_usage();

			if ($usage < 1e3 || $unit == 'B')
			{
				$unit = '';
			}
			elseif ($usage < 9e5 || $unit == 'K')
			{
				$usage = round($usage / 1e3, 2);
				$unit = 'K';
			}
			elseif ($usage < 9e8 || $unit == 'M')
			{
				$usage = round($usage / 1e6, 2);
				$unit = 'M';
			}
			elseif ($usage < 9e11 || $unit = 'G')
			{
				$usage = round($usage / 1e9, 2);
				$unit = 'G';
			}
			else
			{
				$usage = round($usage / 1e12, 2);
				$unit = 'T';
			}

			return [
				'num' => $usage,
				'unit' => $unit,
			];
		}

		/**
		*  Render the profiler output
		*  @method render
		*  @param {int} [showDepth=-1] the depth of the step tree to traverse when rendering the profiler output. -1 to render the entire tree
		*  @return {String} The render of the profiler to include on your page
 		*/
		public function render($showDepth = -1)
		{	
			if (!$this->isEnabled()) return $this->ghostNode;

			$this->end('___GLOBAL_END_PROFILER___', true);

			$this->globalEnd = microtime(true);
			$this->globalDuration = $this->globalEnd - $this->globalStart;

			$this->calculateThreshold();
			$mem = $this->getMemUsage();
			$duration = $this->getGlobalDuration();
			
			list($serverName) = explode('.', php_uname('n'));
			
			// Create the render to render nodes
			$renderer = new ProfilerRenderer($this, $this->topNodes, $showDepth);
			
			$result = $this->_parser->template(
				'Profiler',
				[
					'globalDuration' => $duration,
					'memUsage' => $mem['num'], 
					'memUnit' => $mem['unit'],
					'serverName' => $serverName,
					'title' => substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?')),
					'date' => date('D, d M Y H:i:s T'),
					'queryPercent' => $duration > 0 ? round($this->getTotalQueryTime() / $duration, 2)*  100 : 0,
					'nodes' => $renderer->nodes,
					'queryNodes' => $renderer->queryNodes
				]
			);
			unset($renderer);
			return $result;
		}

		/**
		*  Add node duration to the {@link Profiler::$childDurations} variable
		*  @method addDuration
		*  @param {Number} time duration of the child node in microseconds
		*/
		public function addDuration($time)
		{
			$this->childDurations []= $time;
		}

		/**
		*  Set the Percentile Boundary Threshold. 
		*  This is used to set the percentile boundary for when a node is considered trivial or not.
		*  By default, .75 is used.  This translates to the fastest 25% of nodes being regarded "trivial".
		*  This is a sliding scale, so you will always see some output, regardless of how fast your application runs.
		*  @method setTrivialThreshold
		*  @param {Number} threshold the threshold to use as the percentile boundary
		*/
		static public function setTrivialThreshold($threshold)
		{
			$this->trivialThreshold = $threshold;
		}

		/**
		*  Calculate the time cut-off for a trivial step. 
		*  Utilizes the {@link Profiler::$trivialThreshold} value to determine how fast a step must be to be regarded "trivial"
		*  @method calculateThreshold
		*  @protected
		*/
		protected function calculateThreshold()
		{
			if (count($this->childDurations))
			{
				foreach ($this->childDurations as &$childDuration)
				{
					$childDuration = round($childDuration*  1000, 1);
				}

				sort($this->childDurations);

				$this->trivialThresholdMS = $this->childDurations[ floor(count($this->childDurations)*  $this->trivialThreshold) ];
			}
		}

		/**
		*  Determines if a node is trivial
		*  @method isTrivial
		*  @param {ProfilerNode} node The node to investigate
		*  @return {Boolean} True if a node is trivial, false if not
		*/
		public function isTrivial($node)
		{
			$node_duration = $node->getSelfDuration();

			return $node_duration < $this->trivialThresholdMS;
		}
	}
}