<?php 
//Verifica se SYSTEM foi definido na index.php
if( !defined('SYSTEM') ) exit; 

class Processo{

	private $id;
	private $time_blocked;
	private $status;
	private $fila;
    private $quantum;
	private $n_cpu_bursts = 0;
	private $n_io_bursts = 0;
	private $time_in_cpu;
	private $tempo_espera;
	private $bursts;

	public function __construct( $id, array $bursts ) {
		$this->bursts = $bursts;
		$this->id = $id;
		$this->time_blocked = 0;
		$this->status = 'READY';
		$this->fila = FILA_PADRAO;
        $this->tempo_espera = 0;
        $this->time_in_cpu = 0;
        $this->prox_cpu_burst();
        $this->prox_io_burst();
		$this->set_quantum( constant( FILA_PADRAO ) );
	}

	public function get_id() {
		return $this->id;
	}

	public function set_status( $status ) {
		$this->status = $status;
	}

	public function get_status() {
		return $this->status;
	}

	public function set_fila( $fila ) {
		$this->fila = $fila;
	}

	public function get_fila() {
		return $this->fila;
	}

    public function get_time_in_cpu() {
        return $this->time_in_cpu;
    }

    public function get_n_io_burst() {
    	return $this->n_io_bursts;
    }

    public function get_n_cpu_burst() {
        return $this->n_cpu_bursts;
    }

    public function get_quantum() {
        return $this->quantum;
    }

    public function set_quantum( $valor ) {
        $this->quantum = $valor;
    }

    public function incrementa_time_in_cpu() {
        $this->time_in_cpu++;
    }

    public function decrementa_n_cpu_burst() {
        $this->n_cpu_bursts--;
    }

    public function decrementa_n_io_burst( $valor ) {
    	if( $this->status === 'BLOCK' )
    		$this->time_blocked += $valor;

        $this->n_io_bursts -= $valor;
    }

    public function incrementa_tempo_espera( $valor ) {
    	$this->tempo_espera += $valor;
    }

    public function get_tempo_espera() {
    	return $this->tempo_espera;
    }

    public function get_time_blocked() {
    	return $this->time_blocked;
    }
	
	public function prox_io_burst() {
		$this->time_blocked = 0;
		if( $this->n_io_bursts <= 0 )
			$this->n_io_bursts = ( !empty($this->bursts) ) ? array_shift( $this->bursts ) : 0;	
	}

	public function prox_cpu_burst() {
		if( $this->n_cpu_bursts <= 0 )
			$this->n_cpu_bursts = ( !empty($this->bursts) ) ? array_shift( $this->bursts ) : 0;
	}
	
}