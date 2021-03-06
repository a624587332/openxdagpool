<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Pool\{DataReader, Config, Uptime, Formatter};
use App\Pool\Statistics\{Parser as StatisticsParser, Presenter as StatisticsPresenter, Stat as PoolStat};

use App\Users\Leaderboard;
use App\Miners\Miner;

use Carbon\Carbon;
use Auth;

class StatsController extends Controller
{
	protected $reader, $config, $uptime, $format;

	public function __construct(DataReader $reader, Config $config, Uptime $uptime, Formatter $format)
	{
		$this->reader = $reader;
		$this->config = $config;
		$this->uptime = $uptime;
		$this->format = $format;
	}

	public function index(Leaderboard $leaderboard)
	{
		$stats_presenter = new StatisticsPresenter($stats_parser = new StatisticsParser($this->reader->getStatistics()));
		$pool_hashrate = (float) $stats_parser->getPoolHashrate();
		$user_stats = [];

		if ($user = Auth::user()) {
			$user_hashrate_exact = $user->miners->sum('average_hashrate');

			$user_stats = [
				'user_hashrate' => $this->format->hashrate($user_hashrate_exact),
				'user_miners' => $user->miners->sum('machines_count'),
				'user_balance' => $this->format->balance($user_balance = $user->miners->sum('balance')),
				'user_balance_exact' => $this->format->fullBalance($user_balance),
				'user_earnings' => $this->format->balance($user_earnings = $user->miners->sum('earned')),
				'user_earnings_exact' => $this->format->fullBalance($user_earnings),
				'user_rank' => 'N/A',
			];

			foreach ($leaderboard->get() as $position => $entry) {
				if ($entry['user']->id === $user->id) {
					$user_stats['user_rank'] = '#' . ($position + 1);
					break;
				}
			}
		}

		$pool_stat = PoolStat::orderBy('id', 'desc')->first();

		return response()->json([
			'pool_hashrate' => $this->format->hashrate($stats_parser->getPoolHashrate()),
			'network_hashrate' => $this->format->hashrate($stats_parser->getNetworkHashrate()),
			'blocks' => $stats_parser->getNumberOfBlocks(),
			'main_blocks' => $stats_parser->getNumberOfMainBlocks(),
			'difficulty' => $stats_presenter->getReadableDifficulty(),
			'difficulty_exact' => $stats_presenter->getExactDifficulty(),
			'supply' => $this->format->wholeBalance($stats_parser->getSupply()),

			'miners' => number_format($pool_stat ? $pool_stat->active_miners : 0, 0, '.', ','),

			'fees' => $this->config->getFees(),
			'config' => $this->config->getConfig(),

			'uptime' => $this->uptime->getReadableUptime(),
			'uptime_exact' => $this->uptime->getExactUptime(),
		] + $user_stats);
	}

	public function detailed()
	{
		$pool_hashrate = ['x' => [], 'Pool hashrate (Gh/s)' => []];
		$active_miners = ['x' => [], 'Active pool miners' => []];
		$network_hashrate = ['x' => [], 'Network hashrate (Gh/s)' => []];

		$stats = PoolStat::orderBy('id', 'asc')->where('created_at', '>', Carbon::now()->subDays(3))->get();

		foreach ($stats as $stat) {
			$datetime = $stat->created_at->subMinutes(5)->format('Y-m-d H:i');

			$pool_hashrate['x'][] = $datetime;
			$active_miners['x'][] = $datetime;
			$network_hashrate['x'][] = $datetime;

			$pool_hashrate['Pool hashrate (Gh/s)'][] = $stat->pool_hashrate / 1024 / 1024 / 1024;
			$network_hashrate['Network hashrate (Gh/s)'][] = $stat->network_hashrate / 1024 / 1024 / 1024;

			$active_miners['Active pool miners'][] = $stat->active_miners;
		}

		return response()->json([
			'pool_hashrate' => $pool_hashrate,
			'active_miners' => $active_miners,
			'network_hashrate' => $network_hashrate,
		]);
	}
}
