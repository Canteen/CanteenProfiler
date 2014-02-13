<?php

/**
*  @module Canteen\Profiler
*/
namespace Canteen\Profiler
{
	/**
	 *  Ghost node used as a faux ProfilerNode and ProfilerSQLNode when the Profiler is disabled.
	 *  @class ProfilerGhostNode
	 */
	class ProfilerGhostNode
	{
		/**
		 * @ignore
		 */
		public function __call($method, $params)
		{
			return $this;
		}
	}
}