<?php 
//Verifica se SYSTEM foi definido na index.php
if( !defined('SYSTEM') ) exit; 

class Processo{

	private $id;
	private $time_blocked;
	private $status;
	private $fila;
    private $quantum;
	private $n_cpu_bursts;
	private $n_io_bursts;
	private $time_in_cpu;
	private $tempo_espera;

	public function __construct( $id, $n_cpu_bursts, $n_io_bursts ) {
		$this->time_blocked = 0;
		$this->status = 'READY';
		$this->fila = FILA_PADRAO;
        $this->quantum = constant( FILA_PADRAO );
        $this->tempo_espera = 0;
        $this->time_in_cpu = 0;
		$this->id = $id;
		$this->n_cpu_bursts = $n_cpu_bursts;
		$this->n_io_bursts = $n_io_bursts;
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

    public function get_n_cpu_bursts() {
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

    public function decrementa_n_io_bursts() {
        $this->n_io_bursts--;
    }

    public function decrementa_n_cpu_bursts() {
        $this->n_cpu_bursts--;
    }

    public function incrementa_tempo_espera( $valor ) {
    	$this->tempo_espera += $valor;
    }

    public function get_tempo_espera() {
    	return $this->tempo_espera;
    }
}