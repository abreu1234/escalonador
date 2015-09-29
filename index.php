<?php
ini_set('display_errors',1);
ini_set('display_startup_erros',1);
error_reporting(E_ALL);

class Sistema {

	//Filas de status
	private $status = [
		'RUNNING' 	=> [],
		'READY' 	=> [],
		'BLOCK'		=> []
	];
	//Filas de processos
	private $filas = [
		'RR-10'	=> [],
		'RR-20'	=> [],
		'FIFO'	=> []
	];
	//Lista de processos
	private $processos = [];
	//Tempo de execução total da cpu
	private $time = 0;

	public function __construct() {
		$this->constants();
		$this->includes();

		$this->init();
		$this->escalonador();
	}

	/**
	 * Cria novos objetos de processo
	 */
	private function init() {
		$this->novo_processo(50, 50);
		$this->novo_processo(22, 50);
		$this->novo_processo(90, 50);
		$this->novo_processo(190, 50);
	}

	private function constants() {
		define( 'SYSTEM', true );
		define( 'FILA_PADRAO', 'RR-10' );
		define( 'RR-10', 10 );
		define( 'RR-20', 20 );
		define( 'FIFO', 0 );
	}

	private function includes() {
		include_once 'class.processo.php';
	}

	/**
	 * Adiciona o status a um processo
	 *
	 * @param Processo $processo
	 */
	public function set_status( Processo $processo ) {
		echo "Processo id: ".$processo->get_id()." alterado para o status: ".$processo->get_status();
		echo "<br />";
		$this->status[ $processo->get_status() ][ $processo->get_id() ] =  $processo;
	}

	/**
	 * Troca processo de status
	 *
	 * @param Processo $processo
	 * @param $status_novo
	 */
	public function troca_status( Processo $processo, $status_novo ) {
		$status_ant = $processo->get_status();
		//Remove da lista de status
		unset( $this->status[ $status_ant ][ $processo->get_id() ] );
		//Adiciona na lista de status
		$processo->set_status( $status_novo );
		$this->set_status( $processo );
	}

	/**
	 * Adiciona no final da fila
	 *
	 * @param Processo $processo
	 */
	public function set_fila( Processo $processo ) {
		echo "Processo id: ".$processo->get_id()." alterado para a fila: ".$processo->get_fila();
		echo "<br />";
		$this->filas[ $processo->get_fila() ][ $processo->get_id() ] = [
			'processo' 		=> $processo,
			'cpu_burst_ant'	=> 0
		];
	}

	/**
	 * Remove um processo da fila atual e adiciona em uma fila anterior
	 *
	 * @param Processo $processo
	 * @param $fila_novo
	 */
	public function troca_fila( Processo $processo, $fila_novo ) {
		$fila_ant = $processo->get_fila();
		//Remove da lista de fila
		unset( $this->status[ $fila_ant ][ $processo->get_id() ] );
		//Adiciona na lista de fila
		$processo->set_fila( $fila_novo );
		$this->set_fila( $processo );
	}

	/**
	 * Troca o processo para um prioridade mais baixa
	 *
	 * @param Processo $processo
	 */
	private function rebaixa_fila( Processo $processo ) {
		switch( $processo->get_fila() ) {
			case 'RR-10': $this->troca_fila( $processo, 'RR-20' ); break;
			case 'RR-20': $this->troca_fila( $processo, 'FIFO' ); break;
		}
	}

	/**
	 * Cria um novo processo
	 *
	 * @param int $n_cpu_bursts
	 * @param  int $n_io_bursts
	 * @return Processo retorna o objeto do processo criado
	 */
	public function novo_processo( $n_cpu_bursts, $n_io_bursts) {
		$processos = $this->processos;
		end($processos);
		$ultimo_id = key($processos);
		$ultimo_id = ( !$ultimo_id ) ? 0 : $this->processos[$ultimo_id]->get_id();

		$this->processos[++$ultimo_id] = new Processo( $ultimo_id, $n_cpu_bursts, $n_io_bursts );
		$this->set_status( $this->processos[$ultimo_id] );
		$this->set_fila( $this->processos[$ultimo_id] );

		return $this->processos[$ultimo_id];
	}

	/**
	 * Simula o processador rodando o processo
	 *
	 * @param Processo $processo
	 */
	private function cpu( Processo $processo ) {
		$this->troca_status( $processo, 'RUNNING' );
		$quantum = ( $processo->get_fila() != 'FIFO' ) ? $processo->get_quantum() : $processo->get_n_cpu_bursts();
		$quantum_i = 0;

		while( $quantum_i < $quantum && $processo->get_n_cpu_bursts() > 0 ){
			$this->time++;
			$quantum_i++;
			$processo->incrementa_time_in_cpu();
			$processo->decrementa_n_cpu_bursts();
		}

		$processo->set_quantum( $quantum - $quantum_i );
		return $quantum_i;
	}

	/**
	 * Simula o escalonador de processos
	 */
	private function escalonador() {
		echo '### ESCALONADOR INICIADO ###<br /><br /><br />';
		$total_procs = count( $this->status['READY'] );

		while( $total_procs > 0 ) {
			if( count( $this->filas['RR-10'] ) > 0 )
				$fila = 'RR-10';
			elseif( count( $this->filas['RR-20'] ) > 0 )
				$fila = 'RR-20';
			else
				$fila = 'FIFO';

			$proc_na_cpu = array_shift( $this->filas[$fila] );
			$cpu_burst = $this->cpu( $proc_na_cpu['processo'] );

			echo "Processo id: ".$proc_na_cpu['processo']->get_id()." cpu burst: ".$proc_na_cpu['processo']->get_n_cpu_bursts();
			echo "<br />";
			//Se o n_cpu_bursts for igual a 0 quer dizer que está finalizado
			if( $proc_na_cpu['processo']->get_n_cpu_bursts() > 0 ) {
				$this->troca_status($proc_na_cpu['processo'], 'READY');

				if( $cpu_burst == constant( $fila ) && $proc_na_cpu['cpu_burst_ant'] == constant( $fila )  ) {
					$this->rebaixa_fila( $proc_na_cpu['processo'] );
				}else{
					$proc_na_cpu['cpu_burst_ant'] = $cpu_burst;
					array_push( $this->filas[$fila], $proc_na_cpu );
				}

				//Se quantum for 0 dar nova quantidade de quantum
				if ( $proc_na_cpu['processo']->get_quantum() === 0 ) {
					$proc_na_cpu['processo']->set_quantum(constant($proc_na_cpu['processo']->get_fila()));
				}

			}
			$total_procs = count( $this->status['READY'] );

		}

	}

}

return new Sistema();