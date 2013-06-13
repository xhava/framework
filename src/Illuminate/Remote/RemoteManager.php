<?php namespace Illuminate\Remote;

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\ConsoleOutput;

class RemoteManager {

	/**
	 * The application instance.
	 *
	 * @var \Illuminate\Foundation\Application
	 */
	protected $app;

	/**
	 * The active connection instances.
	 *
	 * @var array
	 */
	protected $connections = array();

	/**
	 * Create a new remote manager instance.
	 *
	 * @param  \Illuminate\Foundation\Application  $app
	 * @return void
	 */
	public function __construct($app)
	{
		$this->app = $app;
	}

	/**
	 * Get a remote connection instance.
	 *
	 * @param  string|array|dynamic  $name
	 * @return \Illuminate\Remote\Connection
	 */
	public function into($name)
	{
		if (is_string($name) or is_array($name))
		{
			return $this->connection($name);
		}
		else
		{
			return $this->connection(func_get_args());
		}
	}

	/**
	 * Get a remote connection instance.
	 *
	 * @param  string|array  $name
	 * @return \Illuminate\Remote\Connection
	 */
	public function connection($name = null)
	{
		if (is_array($name)) return $this->multiple($name);

		return $this->resolve($name ?: $this->getDefaultConnection());
	}

	/**
	 * Resolve a multiple connection instance.
	 *
	 * @param  array  $names
	 * @return \Illuminate\Remote\MultiConnection
	 */
	public function multiple(array $names)
	{
		return new MultiConnection(array_map(array($this, 'resolve'), $names));
	}

	/**
	 * Resolve a remote connection instance.
	 *
	 * @param  string  $name
	 * @return \Illuminate\Remote\Connection
	 */
	public function resolve($name)
	{
		if ( ! isset($this->connections[$name]))
		{
			$this->connections[$name] = $this->makeConnection($name, $this->getConfig($name));
		}

		return $this->connections[$name];
	}

	/**
	 * Make a new connection instance.
	 *
	 * @param  string  $name
	 * @param  array   $config
	 * @return \Illuminate\Remote\Connection
	 */
	protected function makeConnection($name, array $config)
	{
		$this->setOutput($connection = new Connection(

			$name, $config['host'], $config['username'], $this->getAuth($config)

		));

		return $connection;
	}

	/**
	 * Set the output implementation on the connection.
	 *
	 * @param  \Illuminate\Remote\Connection  $connection
	 * @return void
	 */
	protected function setOutput(Connection $connection)
	{
		$output = $this->app->runningInConsole() ? new ConsoleOutput : new NullOutput;

		$connection->setOutput($output);
	}

	/**
	 * Format the appropriate authentication array payload.
	 *
	 * @param  array  $config
	 * @return array
	 */
	protected function getAuth(array $config)
	{
		if (isset($config['key']))
		{
			return array('key' => $config['key']);
		}
		elseif (isset($config['password']))
		{
			return array('password' => $config['password']);
		}

		throw new \InvalidArgumentException('Password / key is required.');
	}

	/**
	 * Get the configuration for a remote server.
	 *
	 * @param  string  $name
	 * @return array
	 */
	protected function getConfig($name)
	{
		$config = $this->app['config']['remote.connections.'.$name];

		if ( ! is_null($config)) return $config;

		throw new \InvalidArgumentException("Remote connection [$name] not defined.");
	}

	/**
	 * Get the default connection name.
	 *
	 * @return string
	 */
	protected function getDefaultConnection()
	{
		return $this->app['config']['remote.default'];
	}

	/**
	 * Dynamically pass methods to the default connection.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 */
	public function __call($method, $parameters)
	{
		return call_user_func_array(array($this->connection(), $method), $parameters);
	}

}