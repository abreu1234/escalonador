<?php

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
	//Processo atual usando a cpu
	private $proc_na_cpu = NULL;



	public function __construct() {
		$this->constants();
		$this->includes();

		$this->init();
		$this->escalonador();
		$this->mostra_tempo();
	}

	/**
	 * Cria novos objetos de processo
	 */
	private function init() {
		printf("### INICIANDO PROCESSOS ###".QUEBRA.QUEBRA);
		$this->novo_processo(50, 10, 21, 11, 5);
		$this->novo_processo(20, 5, 15, 2, 5);
		$this->novo_processo(120);
		$this->novo_processo(190);
	}

	private function constants() {
		define( 'SYSTEM', true );
		define( 'FILA_PADRAO', 'RR-10' );
		//Valor do quantum de cada fila
		define( 'RR-10', 10 );
		define( 'RR-20', 20 );
		define( 'FIFO', 0 );
		//Peso da prioridade, quanto o número menor maior a prioridade
		define( 'RR-10_P', 1 );
		define( 'RR-20_P', 2 );
		define( 'FIFO_P', 3 );

		define( 'TEMPO_ESPERA_MAX', 100 );
		//Define a quebra de linha para funcionar no browser ou terminal linux
		define( 'QUEBRA', (isset($_SERVER['DESKTOP_SESSION'])) ? chr(13).chr(10) : '<br />' );
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
		$this->status[ $processo->get_status() ][ $processo->get_id() ] =  $processo;
	}

	/**
	 * Troca processo de status
	 *
	 * @param Processo $processo
	 * @param String $status_novo
	 */
	public function troca_status( Processo $processo, $status_novo ) {

		if( $status_novo == 'BLOCK' ) {
			printf( 'Tempo %d: Processo id: %d foi bloqueado'.QUEBRA, $this->time, $processo->get_id() );
			unset( $this->filas[ $processo->get_fila() ][ $processo->get_id() ] );

		}elseif( $processo->get_status() === 'BLOCK' && $status_novo === 'READY' ) {
			printf( 'Tempo %d: Processo id: %d passou %dms bloqueado'.QUEBRA, 
				$this->time, $processo->get_id(), $processo->get_time_blocked() 
			);
			$this->set_fila( $processo );
		}

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
	public function set_fila( Processo $processo, $tipo = 'new' ) {

		switch( $tipo ) {
			case 'new': 
				printf( "Tempo %s: Processo %d entra na fila %s ".QUEBRA, $this->time, $processo->get_id(), $processo->get_fila() );	
			break;
			case 'up': 
				printf( "Tempo %s: Processo %d promovido para fila %s ".QUEBRA, $this->time, $processo->get_id(), $processo->get_fila() );	
			break;
			case 'down': 
				printf( "Tempo %s: Processo %d regredido para fila %s ".QUEBRA, $this->time, $processo->get_id(), $processo->get_fila() );	
			break;
		}
		
		$this->filas[ $processo->get_fila() ][ $processo->get_id() ] = $processo;

	}

	/**
	 * Remove um processo da fila atual e adiciona em uma fila anterior
	 *
	 * @param Processo $processo
	 * @param String $fila_novo
	 */
	public function troca_fila( Processo $processo, $fila_novo ) {

		$fila_ant = $processo->get_fila();
		//Remove da lista de fila
		unset( $this->status[ $fila_ant ][ $processo->get_id() ] );

		//Verifica se a fila anterior é de maior ou menor prioridade
		if( constant($fila_ant.'_P') < constant($fila_novo.'_P') )
			$tipo = 'down';
		elseif( constant($fila_ant.'_P') > constant($fila_novo.'_P') )
			$tipo = 'up';
		else 
			$tipo = 'new';

		//Reseta os contadores do processo e seta o quantum da nova fila
		$processo->set_quantum( constant( $fila_novo ) );
		$this->processos[ $processo->get_id() ]['cpu_burst_ant'] = 0;
		$this->processos[ $processo->get_id() ]['tempo_espera'] = 0;
		//Adiciona na lista de fila
		$processo->set_fila( $fila_novo );
		$this->set_fila( $processo, $tipo );

	}

	/**
	 * Troca o processo para um prioridade mais baixa
	 *
	 * @param Processo $processo
	 */
	private function rebaixa_processo( Processo $processo ) {

		switch( $processo->get_fila() ) {
			case 'RR-10': $this->troca_fila( $processo, 'RR-20' ); break;
			case 'RR-20': $this->troca_fila( $processo, 'FIFO' ); break;
		}

	}

	/**
	 * Promove um processo para a fila de maior prioridade
	 *
	 * @param Processo $processo
	 */
	private function promove_processo( Processo $processo ) {

		switch( $processo->get_fila() ) {
			case 'FIFO': $this->troca_fila( $processo, 'RR-20' ); break;
			case 'RR-20': $this->troca_fila( $processo, 'RR-10' ); break;
		}

	}

	/**
	 * Cria um novo processo e adiciona ao array de processos
	 *
	 * @param int $bursts 
	 * @return Processo retorna o objeto do processo criado
	 */
	public function novo_processo( ... $bursts ) {

		$processos = $this->processos;
		end($processos);
		$ultimo_id = key($processos);
		$ultimo_id = ( !$ultimo_id ) ? 0 : $this->processos[$ultimo_id]['processo']->get_id();

		$this->processos[ ++$ultimo_id ] = [
			'processo' 			=> new Processo( $ultimo_id, $bursts ),
			'cpu_burst_ant'		=> 0,
			'tempo_espera'		=> 0
		];

		printf("Processo id: ".$ultimo_id. " criado".QUEBRA);

		$this->set_status( $this->processos[$ultimo_id]['processo'] );
		$this->set_fila( $this->processos[$ultimo_id]['processo'] );

		return $this->processos[$ultimo_id]['processo'];

	}

	/**
	 * Passa por todos os processos não finalizados para verificar se mudou algo
	 *
	 * @param int $cpu_burst
	 */
	private function verifica_processos( $cpu_burst ) {

		foreach( $this->processos as $proc ) {
			$proc = $proc['processo'];
			if( $proc->get_status() === 'READY' ) {
				//Incrementa o tempo de espera atual e do processo
				$this->processos[ $proc->get_id() ]['tempo_espera'] += $cpu_burst;
				$proc->incrementa_tempo_espera( $cpu_burst );

				//Se tempo de espera atual for maior que o tempo maximo o processo é promovido
				if( $this->processos[ $proc->get_id() ]['tempo_espera'] >= TEMPO_ESPERA_MAX ) {
					$this->promove_processo( $proc );
					$this->processos[ $proc->get_id() ]['tempo_espera'] = 0;
				}

			}else if( $proc->get_status() === 'BLOCK' ) {
				//Se completou o io burst troca pra ready
				if( $proc->get_n_io_burst() <= 0 ) {				
					$this->troca_status( $proc, 'READY' );
					$proc->prox_io_burst();
				}else {
					$proc->decrementa_n_io_burst( $cpu_burst );
				}
			}
		}

	}

	/**
	 * Escolhe o primeiro processo da fila com maior prioridade
	 */
	private function escolhe_processo() {
		//Verifica as filas que contém processos por prioridade
		if( count( $this->filas['RR-10'] ) > 0 )
				$fila = 'RR-10';
		elseif( count( $this->filas['RR-20'] ) > 0 )
			$fila = 'RR-20';
		else
			$fila = 'FIFO';

		//Remove o processo da fila
		$proc_na_cpu = array_shift( $this->filas[$fila] );
		$proc_id = $proc_na_cpu->get_id();
		//Marca como usando na cpu
		$this->proc_na_cpu = $this->processos[ $proc_id ];

	}

	/**
	 * Seta um processo como finalizado
	 */
	private function finaliza_processo() {
		
		if( !is_null($this->proc_na_cpu) ) {

			$this->proc_na_cpu['processo']->set_status( 'FINISHED' );
			if( $this->proc_na_cpu['processo']->get_quantum() === constant( $this->proc_na_cpu['processo']->get_fila() ) )
				printf("Tempo %d: Processo id: %d completou seu quantum".QUEBRA, $this->time, $this->proc_na_cpu['processo']->get_id() );
			else
				printf("Tempo %d: Processo id: %d completou antes do quantum".QUEBRA, $this->time, $this->proc_na_cpu['processo']->get_id() );		

		}

	}

	/**
	 * Seta processo como pronto e adiciona no final de uma fila
	 *
	 * @param int $cpu_burst
	 */
	private function processo_pronto( $cpu_burst ) {

		$this->troca_status($this->proc_na_cpu['processo'], 'READY');
		$fila = $this->proc_na_cpu['processo']->get_fila();
		$proc_id = $this->proc_na_cpu['processo']->get_id();

		//Se o processo utilizou todo o burst 2x seguidas ele é rebaixado de fila
		if( $cpu_burst == constant( $fila ) && $this->proc_na_cpu['cpu_burst_ant'] == constant( $fila )  ) {
			$this->rebaixa_processo( $this->proc_na_cpu['processo'] );

		}else{
			//Adicionando o processo no final da fila
			$this->processos[$proc_id]['cpu_burst_ant'] = $cpu_burst;
			array_push( $this->filas[$fila], $this->proc_na_cpu['processo'] );

		}
		//Se quantum for 0 dar nova quantidade de quantum
		if ( $this->proc_na_cpu['processo']->get_quantum() === 0 ) 
			$this->proc_na_cpu['processo']->set_quantum(constant($this->proc_na_cpu['processo']->get_fila()));	

	}

	/**
	 * Mostra o tempo de todos os processos
	 */
	public function mostra_tempo() {
		printf(QUEBRA."### INICIANDO DETALHES DOS PROCESSOS ###".QUEBRA.QUEBRA);

		foreach( $this->processos as $processo ) {
			$proc = $processo['processo'];
			printf("Processo id: %d %dms na CPU, %dms de espera, ultima fila: %s".QUEBRA, 
				$proc->get_id(), $proc->get_time_in_cpu(), $proc->get_tempo_espera(), $proc->get_fila()
			);
		}
	}
	
	/**
	 * Simula o processador rodando o processo
	 *
	 * @param Processo $processo
	 */
	private function cpu( Processo $processo ) {	
		printf('Tempo %d: Escolheu processo id: %d da fila %s'.QUEBRA, $this->time, $processo->get_id(), $processo->get_fila());
		
		$this->troca_status( $processo, 'RUNNING' );
		$quantum = ( $processo->get_fila() != 'FIFO' ) ? $processo->get_quantum() : $processo->get_n_cpu_burst();
		$quantum_i = 0;

		//Roda o processo enquanto ainda tiver creditos quantum e ainda estiver bursts no processo
		while( $quantum_i < $quantum && $processo->get_n_cpu_burst() > 0 ){
			$this->time++;
			$quantum_i++;
			$processo->incrementa_time_in_cpu();
			$processo->decrementa_n_cpu_burst();
		}
		//Se terminar o cpu burst pega o proximo 
		if( $processo->get_n_cpu_burst() <= 0 ) {					
			//caso tenha io burst bloqueia
			if( $processo->get_n_io_burst() > 0 ) 
				$this->troca_status( $processo, 'BLOCK' );

			$processo->prox_cpu_burst();
		}
		
		$processo->set_quantum( $quantum - $quantum_i );
		return $quantum_i;

	}

	/**
	 * Simula o escalonador de processos
	 */
	private function escalonador() {
		printf(QUEBRA."### INICIANDO ESCALONADOR ###".QUEBRA.QUEBRA);

		$total_procs = count( $this->status['READY'] );

		//Roda enquanto estiver proecssos com status READY
		while( $total_procs > 0 ) {

			$this->escolhe_processo();
			$proc_id = $this->proc_na_cpu['processo']->get_id();
			$cpu_burst = $this->cpu( $this->proc_na_cpu['processo'] );

			if(  $this->proc_na_cpu['processo']->get_status() != 'BLOCK' ) {
				//Se o n_cpu_bursts for igual a 0 quer dizer que está finalizado
				if( $this->proc_na_cpu['processo']->get_n_cpu_burst() > 0 )
					$this->processo_pronto( $cpu_burst );
				else
					$this->finaliza_processo();
			}
			
			$this->verifica_processos( $cpu_burst );
			$total_procs = count( $this->status['READY'] );
			$this->processos[ $this->proc_na_cpu['processo']->get_id() ]['tempo_espera'] = 0;
			
		}

	}

}

return new Sistema();