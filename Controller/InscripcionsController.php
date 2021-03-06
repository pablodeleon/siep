<?php
App::uses('AppController', 'Controller');

class InscripcionsController extends AppController {

	var $name = 'Inscripcions';
    var $paginate = array('Inscripcion' => array('limit' => 4, 'order' => 'Inscripcion.fecha_alta DESC'));
		
	function beforeFilter(){
	    parent::beforeFilter();
		/* ACCESOS SEGÚN ROLES DE USUARIOS (INICIO).
        *Si el usuario tiene un rol de superadmin le damos acceso a todo. Si no es así (se trata de un usuario "admin o usuario") tendrá acceso sólo a las acciones que les correspondan.
        */
        if (($this->Auth->user('role') === 'superadmin') || ($this->Auth->user('role') === 'admin')) {
	        $this->Auth->allow();
	    } elseif ($this->Auth->user('role') === 'usuario') { 
	        $this->Auth->allow('index', 'view');
	    }
	    /* FIN */
        /* FUNCIÓN PRIVADA "LISTS" (INICIO).
        *Si se ejecutan las acciones add/edit activa la función privada "lists".
		*/
		if ($this->ifActionIs(array('add', 'edit'))) {
			$this->__lists();
		}
		/* FIN */
    }
	
	public function index() {
		$this->Inscripcion->recursive = 1;
		$this->paginate['Inscripcion']['limit'] = 4;
		$this->paginate['Inscripcion']['order'] = array('Inscripcion.fecha_alta' => 'DESC');
		/* PAGINACIÓN SEGÚN ROLES DE USUARIOS (INICIO).
		*Sí el usuario es "admin" muestra los cursos del establecimiento. Sino sí es "usuario" externo muestra los cursos del nivel.
		*/
		//$cicloIdActual = $this->getLastCicloId();
		$userCentroId = $this->getUserCentroId();
		$userRole = $this->Auth->user('role');
		if ($this->Auth->user('role') === 'admin') {
		//$this->paginate['Inscripcion']['conditions'] = array('Inscripcion.ciclo_id' => $cicloIdActual, 'Inscripcion.centro_id' => $userCentroId);
		$this->paginate['Inscripcion']['conditions'] = array('Inscripcion.centro_id' => $userCentroId);	
		} else if ($userRole === 'usuario') {
			$this->loadModel('Centro');
			$nivelCentro = $this->Centro->find('list', array('fields'=>array('nivel_servicio'), 'conditions'=>array('id'=>$userCentroId)));	
			$nivelCentroId = $this->Centro->find('list', array('fields'=>array('id'), 'conditions'=>array('nivel_servicio'=>$nivelCentro))); 		
			$this->paginate['Inscripcion']['conditions'] = array('Inscripcion.centro_id' => $nivelCentroId);
		}
		/* FIN */
    	/* PAGINACIÓN SEGÚN CRITERIOS DE BÚSQUEDAS (INICIO).
		*Pagina según búsquedas simultáneas ya sea por CICLO y/o CENTRO y/o LEGAJO y/o ESTADO.
		*/
    	$this->redirectToNamed();
		$conditions = array();
		if (!empty($this->params['named']['ciclo_id'])) {
			$conditions['Inscripcion.ciclo_id ='] = $this->params['named']['ciclo_id'];
		}
		if (!empty($this->params['named']['centro_id'])) {
			$conditions['Inscripcion.centro_id ='] = $this->params['named']['centro_id'];
		}
		if (!empty($this->params['named']['legajo_nro'])) {
			$conditions['Inscripcion.legajo_nro ='] = $this->params['named']['legajo_nro'];
		}
		if(!empty($this->params['named']['estado'])) {
			$conditions['Inscripcion.estado ='] = $this->params['named']['estado'];
		}
		$inscripcions = $this->paginate('Inscripcion',$conditions);
		/* FIN */
		/* SETS DE DATOS PARA COMBOBOX (INICIO). */
		$alumnoId = $this->Inscripcion->Alumno->find('list', array('fields'=>array('id', 'persona_id')));
		$this->loadModel('Persona');
        $alumnoNombre = $this->Persona->find('list', array('fields'=>array('id', 'nombre_completo_persona')));    
		$this->loadModel('Centro');
		$nivelCentro = $this->Centro->find('list', array('fields'=>array('nivel_servicio'), 'conditions'=>array('id'=>$userCentroId)));	
		$nivelCentroId = $this->Centro->find('list', array('fields'=>array('id'), 'conditions'=>array('nivel_servicio'=>$nivelCentro)));
		$centros = $this->Inscripcion->Centro->find('list', array('fields'=>array('id', 'sigla'), 'conditions'=>array('id'=>$nivelCentroId)));
		$ciclos = $this->Inscripcion->Ciclo->find('list', array('fields'=>array('id', 'nombre')));
		/* FIN */
		$this->set(compact('inscripcions', 'alumnoNombre', 'centros', 'ciclos'));
	}
    
	public function view($id = null) {
		if (!$id) {
			$this->Session->setFlash('Inscripcion no valida.', 'default', array('class' => 'alert alert-warning'));
			$this->redirect(array('action' => 'index'));
		}
		$this->set('inscripcion', $this->Inscripcion->read(null, $id));

		$alumnoId = $this->Inscripcion->Alumno->find('list', array('fields'=>array('id', 'persona_id')));
		$this->loadModel('Persona');
        $alumnoNombre = $this->Persona->find('list', array('fields'=>array('id', 'nombre_completo_persona')));
        $this->set(compact('inscripcions', 'alumnoNombre'));
	}
        
	public function add() {
		  //abort if cancel button was pressed  
          if(isset($this->params['data']['cancel'])){
                $this->Session->setFlash('Los cambios no fueron guardados. Agregación cancelada.', 'default', array('class' => 'alert alert-warning'));
                $this->redirect( array( 'action' => 'index' ));
		  }
		  if (!empty($this->data)) {
			$this->Inscripcion->create();
 		    //Antes de guardar genera el nombre del agente
			$this->request->data['Inscripcion']['empleado_id'] = $this->Auth->user('empleado_id');
			//Genera el centro id del usuario y se deja en los datos que se intentaran guardar
		    $userCentroId = $this->getUserCentroId();
		    $this->request->data['Inscripcion']['centro_id'] = $userCentroId;
			//Genera el ciclo id y se deja en los datos que se intentaran guardar
			$cicloId = $this->getLastCicloId();
			$this->request->data['Inscripcion']['ciclo_id'] = $cicloId;
			//Antes de guardar genera el número de legajo del Alumno.
			$ciclos = $this->Inscripcion->Ciclo->findById($cicloId, 'nombre');
			$ciclo = substr($ciclos['Ciclo']['nombre'], -2);
			$alumnoId = $this->request->data['Inscripcion']['alumno_id'];
			$personaDoc = $this->Inscripcion->Alumno->Persona->findById($alumnoId, 'documento_nro');
            //$personaDoc = $personasDoc['Persona']['documento_nro'];
			//Genera el nro de legajo y se deja en los datos que se intentaran guardar
			$codigoActual = $this->__getCodigo($ciclo, $personaDoc);
			//Comprueba que ese legajo no exista.
			$codigoLista = $this->Inscripcion->find('list', array('fields'=>array('legajo_nro')));
            if (in_array($codigoActual, $codigoLista, true))
            { 
                $this->Session->setFlash('El alumno ya está inscripto en este ciclo.', 'default', array('class' => 'alert alert-danger'));
			}else{
				$this->request->data['Inscripcion,']['legajo_nro'] = $this->__getCodigo($ciclo, $personaDoc);
			//Antes de guardar genera el estado de la inscripción
			    if($this->request->data['Inscripcion']['fotocopia_dni'] == true && $this->request->data['Inscripcion']['certificado_septimo'] == true && $this->request->data['Inscripcion']['analitico'] == true){
			        $estado = "COMPLETA";	
			    }else{
			        $estado = "PENDIENTE";
			    }
			//Genera el estado y se deja en los datos que se intentaran guardar
			    $this->request->data['Inscripcion']['estado'] = $estado;
				if ($this->Inscripcion->save($this->data)) {
					$this->Session->setFlash('La inscripcion ha sido grabada.', 'default', array('class' => 'alert alert-success'));
					$inserted_id = $this->Inscripcion->id;
					$this->redirect(array('action' => 'view', $inserted_id));
				} else {
					$this->Session->setFlash('La inscripcion no fue grabada. Intente nuevamente.', 'default', array('class' => 'alert alert-danger'));
				}
			}
		}
    }
        
	public function edit($id = null) {
		if (!$id && empty($this->data)) {
			$this->Session->setFlash('Inscripcion no valida.', 'default', array('class' => 'alert alert-warning'));
			$this->redirect(array('action' => 'index'));
		}
		if (!empty($this->data)) {
		  //abort if cancel button was pressed  
            if(isset($this->params['data']['cancel'])){
                $this->Session->setFlash('Los cambios no fueron guardados. Edición cancelada.', 'default', array('class' => 'alert alert-warning'));
                $this->redirect( array( 'action' => 'index' ));
		    }
			//Antes de guardar genera el estado de la inscripción
			if($this->request->data['Inscripcion']['fotocopia_dni'] == true && $this->request->data['Inscripcion']['certificado_septimo'] == true && $this->request->data['Inscripcion']['certificado_laboral'] == true){
			   $estado = "COMPLETA";	
			}else{
			   $estado = "PENDIENTE";
			}
			//Se genera el estado y se deja en los datos que se intentaran guardar
			$this->request->data['Inscripcion']['estado'] = $estado;
			if ($this->Inscripcion->save($this->data)) {
				$this->Session->setFlash('La inscripcion ha sido grabada.', 'default', array('class' => 'alert alert-success'));
				$inserted_id = $this->Inscripcion->id;
				$this->redirect(array('action' => 'view', $inserted_id));
			} else {
				$this->Session->setFlash('La inscripcion no fue grabada. Intente nuevamente.', 'default', array('class' => 'alert alert-danger'));
			}
		}
		if (empty($this->data)) {
			$this->data = $this->Inscripcion->read(null, $id);
		}
	}
    
    public function delete($id = null) {
		if (!$id) {
			$this->Session->setFlash('Id no valida para inscripcion.', 'default', array('class' => 'alert alert-warning'));
			$this->redirect(array('action'=>'index'));
		}
		if ($this->Inscripcion->delete($id)) {
			$this->Session->setFlash('La Inscripcion ha sido borrada.', 'default', array('class' => 'alert alert-success'));
			$this->redirect(array('action'=>'index'));
		}
		$this->Session->setFlash('La Inscripcion no fue borrada. Intentelo nuevamente.', 'default', array('class' => 'alert alert-danger'));
		$this->redirect(array('action' => 'index'));
	}
	
	//Métodos privados
	private function __lists(){
	    $this->loadModel('User');
        $ciclos = $this->Inscripcion->Ciclo->find('list');
		$cicloIdActual = $this->getLastCicloId();
		$centros = $this->Inscripcion->Centro->find('list');
		$this->loadModel('Empleado');
		$empleados = $this->Inscripcion->Empleado->find('list', array('fields'=>array('id', 'nombre_completo_empleado'), 'conditions'=>array('id'== 'empleadoId')));
		//Sí es "superadmin" o "usuario" ve combobox con todos los cursos. Sino ve los propios del centro.
		$userRol = $this->Auth->user('role');
		if (($userRol == 'superadmin') || ($userRol == 'usuario')) {
			$cursos = $this->Inscripcion->Curso->find('list', array('fields'=>array('id','nombre_completo_curso')));
		} else if ($userRol == 'admin') {
			$userCentroId = $this->getUserCentroId();
			$cursos = $this->Inscripcion->Curso->find('list', array('fields'=>array('id','nombre_completo_curso'), 'conditions'=>array('centro_id'=>$userCentroId)));	
		}
		$materias = $this->Inscripcion->Materia->find('list');
    	//Sí es "superadmin" o "usuario" ve combobox con todos los alumnos. Sino ve los propios del centro.
		$userRol = $this->Auth->user('role');
		if (($userRol == 'superadmin') || ($userRol == 'usuario')) {
			$personaId = $this->Inscripcion->Alumno->find('list', array('fields'=>array('persona_id')));
		} else if ($userRol == 'admin') {
			$userCentroId = $this->getUserCentroId();
			$alumnos = $this->Inscripcion->Alumno->find('list', array('fields'=>array('id'), 'conditions'=>array('centro_id'=>$userCentroId)));
		}
		$personaId = $this->Inscripcion->Alumno->find('list', array('fields'=>array('persona_id'), 'conditions'=>array('id'=>$alumnos)));
		$this->loadModel('Persona');
		$personaNombre = $this->Inscripcion->Alumno->Persona->find('list', array('fields'=>array('nombre_completo_persona'), 'conditions'=>array('id'=>$personaId)));
		print_r($personaNombre);
		$this->set(compact('alumnos', 'ciclos', 'centros', 'cursos', 'materias', 'empleados', 'cicloIdActual'));
	}
	
	private function __getCodigo($ciclo, $personaDoc){
		$legajo= $personaDoc."-".$ciclo;
		return $legajo;
    }
}
?>
