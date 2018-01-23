<?php

namespace App\Pool\Miners;

use App\Pool\BaseParser;

class Parser extends BaseParser
{
	public function getNumberOfMiners()
	{
		$this->forEachLine(function($line, 4) {
			$parts = preg_split('/\s+/siu', $line);
		});
	}

	public function getNumberOfActiveMiners()
	{
		$active = 0;

		foreach ($this->list as $miner) {
			if ($miner->getStatus() === 'active')
				$active++;
		}

		return $active;
	}

	public function getTotalUnpaidShares()
	{
		$total = 0;
		foreach ($this->list as $miner)
			$total += $miner->getUnpaidShares();

		return $total;
	}

	public function getMiner($address)
	{
		foreach ($this->list as $miner) {
			if ($miner->getAddress() === $address)
				return $miner;
		}

		return null;
	}

	protected function parse()
	{
		array_shift($this->lines);
		array_shift($this->lines);
		array_shift($this->lines);

		foreach ($this->lines as $line) {
			$parts = preg_split('/\s+/siu', $line);

			if (count($parts) !== 6)
				continue;

			if ($parts[0] === '-1.')
				continue;

			if ($miner = $this->getMiner($parts[1])) {
				$miner->addIpAndPort($parts[3]);
				$miner->addUnpaidShares($parts[5]);

				if ($miner->getStatus() !== 'active' && $parts[2] === 'active')
					$miner->setStatus($parts[2]);

				continue;
			}

			$this->list[] = new Miner($parts[1], $parts[2], $parts[3], $parts[4], $parts[5]);
		}
	}
}
