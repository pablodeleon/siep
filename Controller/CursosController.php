<?php
App::uses('AppController', 'Controller');

class CursosController extends AppController {

	var $name = 'Cursos';
    public $uses = array('Curso', 'Inscripcion');
	var $paginate = array('Curso' => array('limit' => 6, 'order' => 'Curso.anio ASC'));

	public function beforeFilter() {
        parent::beforeFilter();
        /* ACCESOS SEGÚN ROLES DE USUARIOS (INICIO).
        *Si el usuario tiene un rol de superadmin le damos acceso a todo. Si no es así (se trata de un usuario "admin o usuario") tendrá acceso sólo a las acciones que les correspondan.
        */
        if (($this->Auth->user('role') === 'superadmin')  || ($this->Auth->user('role') === 'admin')) {
	        $this->Auth->allow();
	    } elseif ($this->Auth->user('role') === 'usuario') { 
	        $this->Auth->allow('index', 'view');
	    }
	    /* FIN */ 
    } 

	function index() {
		$this->Curso->recursive = 1;
		$this->paginate['Curso']['limit'] = 6;
		$this->paginate['Curso']['order'] = array('Curso.anio' => 'ASC');
		/* PAGINACIÓN SEGÚN ROLES DE USUARIOS (INICIO).
		*Sí el usuario es "admin" muestra los cursos del establecimiento. Sino sí es "usuario" externo muestra los cursos del nivel.
		*/ 
		$userCentroId = $this->getUserCentroId();
		$userRole = $this->Auth->user('role');
		if ($userRole === 'admin') {
		$this->paginate['Curso']['conditions'] = array('Curso.centro_id' => $userCentroId);
		} else if ($userRole === 'usuario') {
			$nivelCentro = $this->Curso->Centro->find('list', array('fields'=>array('nivel_servicio'), 'conditions'=>array('id'=>$userCentroId)));
			$nivelCentroId = $this->Curso->Centro->find('list', array('fields'=>array('id'), 'conditions'=>array('nivel_servicio'=>$nivelCentro))); 		
			$this->paginate['Curso']['conditions'] = array('Curso.centro_id' => $nivelCentroId);
		}
		/* FIN */
		/* PAGINACIÓN SEGÚN CRITERIOS DE BÚSQUEDAS (INICIO).
		*Pagina según búsquedas simultáneas ya sea por CENTRO y/o ANIO y/o DIVISIÓN y/o TURNO.
		*/
		$this->redirectToNamed();
		$conditions = array();
		if (!empty($this->params['named']['centro_id'])) {
			$conditions['Curso.centro_id ='] = $this->params['named']['centro_id'];
		}
		if (!empty($this->params['named']['anio'])) {
			$conditions['Curso.anio ='] = $this->params['named']['anio'];
		}
		if (!empty($this->params['named']['division'])) {
			$conditions['Curso.division ='] = $this->params['named']['division'];
		}
		if (!empty($this->params['named']['turno'])) {
			$conditions['Curso.turno ='] = $this->params['named']['turno'];
		}
		$cursos = $this->paginate('Curso',$conditions);
	    /* FIN */
		/* SETS DE DATOS PARA COMBOBOX (INICIO). */
		$nivelCentro = $this->Curso->Centro->find('list', array('fields'=>array('nivel_servicio'), 'conditions'=>array('id'=>$userCentroId)));
		$nivelCentroId = $this->Curso->Centro->find('list', array('fields'=>array('id'), 'conditions'=>array('nivel_servicio'=>$nivelCentro)));
		$centros = $this->Curso->Centro->find('list', array('fields'=>array('sigla'), 'conditions'=>array('id'=>$nivelCentroId)));
		/* FIN */
		$this->set(compact('cursos', 'centros'));
	}

	function view($id = null) {
		if (!$id) {
			$this->Session->setFlash('Curso no valido.', 'default', array('class' => 'alert alert-danger'));
			$this->redirect(array('action' => 'index'));
		}
		$this->set('curso', $this->Curso->read(null, $id));
		
		//genera nombres para datos relacionados.
		$inscripcionAlumnoId = $this->Curso->Inscripcion->find('list', array('fields'=>array('alumno_id')));
		$personaId = $this->Curso->Inscripcion->Alumno->find('list', array('fields'=>array('persona_id'), 'conditions'=>array('id'=>$inscripcionAlumnoId)));
		$this->loadModel('Persona');
		$personaNombre = $this->Persona->find('list', array('fields'=>array('nombre_completo_persona'), 'conditions'=>array('id'=>$personaId)));

		//genera número de matriculados.
		$cursoId = $this->Curso->id;
		$alumnosInscriptos = $this->Curso->CursosInscripcion->find('list', array('fields'=>array('curso_id'), 'conditions'=>array('CursosInscripcion.curso_id'=>$cursoId)));
		$matriculados = (count($alumnosInscriptos));

		$this->set(compact('personaNombre', 'cicloNombre', 'matriculados'));
	}

	function add() {
		//abort if cancel button was pressed  
        if(isset($this->params['data']['cancel'])){
                $this->Session->setFlash('Los cambios no fueron guardados. Agregación cancelada.', 'default', array('class' => 'alert alert-warning'));
                $this->redirect( array( 'action' => 'index' ));
		}
		if (!empty($this->data)) {
			$this->Curso->create();
			
			//Antes de guardar obtiene el ciclo actual.
			$this->request->data['Curso']['ciclo_id'] = $this->getLastCicloId();
			//Antes de guardar obtiene el centro del que pertenece el empleado.
			$centroId = $this->Auth->user('centro_id');
			$this->request->data['Curso']['centro_id'] = $centroId;
			//Antes de guardar sí el nivel_servicio del centro es INICIAL o PRIMARIO, obtiene la titulación.
            $this->loadModel('Centro');
            $centroNivel = $this->Centro->find('list', array('fields'=>array('nivel_servicio'), 'conditions'=>array('id'=>$centroId)));
			if ($centroNivel == 'INICIAL') {
				$this->request->data['Curso']['titulacion_id'] = 9;
			} else if ($centroNivel == 'PRIMARIA') {
				$this->request->data['Curso']['titulacion_id'] = 10;

			}
			if ($this->Curso->save($this->data)) {
				$this->Session->setFlash('La sección ha sido grabada.', 'default', array('class' => 'alert alert-success'));
				//$this->redirect(array('action' => 'index'));
				$inserted_id = $this->Curso->id;
				$this->redirect(array('action' => 'view', $inserted_id));
			} else {
				$this->Session->setFlash('La sección no fué grabada. Intentelo nuevamente.', 'default', array('class' => 'alert alert-danger'));
			}
		}
		$titulacions = $this->Curso->Titulacion->find('list');
		$materias = $this->Curso->Materia->find('list');
		$ciclos = $this->Curso->Ciclo->find('list');
		$inscripcions = $this->Inscripcion->find('list');
		$this->set(compact('titulacions', 'materias', 'ciclos', 'inscripcions', $inscripcions));
	}

	function edit($id = null) {
		if (!$id && empty($this->data)) {
			$this->Session->setFlash('Sección no valida.', 'default', array('class' => 'alert alert-warning'));
			$this->redirect(array('action' => 'index'));
		}
		if (!empty($this->data)) {
		  	//abort if cancel button was pressed  
          	if(isset($this->params['data']['cancel'])){
               $this->Session->setFlash('Los cambios no fueron guardados. Edición cancelada.', 'default', array('class' => 'alert alert-warning'));
               $this->redirect( array( 'action' => 'index' ));
		  	}
		  	//Antes de guardar sí el nivel_servicio del centro es INICIAL o PRIMARIO, obtiene la titulación.
            $this->loadModel('Centro');
            $centroNivel = $this->Centro->find('list', array('fields'=>array('nivel_servicio'), 'conditions'=>array('id'=>$centroId)));
			if ($centroNivel == 'INICIAL') {
				$this->request->data['Curso']['titulacion_id'] = 9;
			} else if ($centroNivel == 'PRIMARIA') {
				$this->request->data['Curso']['titulacion_id'] = 10;
			}
		  	if ($this->Curso->save($this->data)) {
				$this->Session->setFlash('La sección ha sido grabada.', 'default', array('class' => 'alert alert-success'));
				//$this->redirect(array('action' => 'index'));
				$inserted_id = $this->Curso->id;
				$this->redirect(array('action' => 'view', $inserted_id));
			} else {
				$this->Session->setFlash('La sección no fue grabada. Intentelo nuevamente.', 'default', array('class' => 'alert alert-danger'));
			}
		}
		if (empty($this->data)) {
			$this->data = $this->Curso->read(null, $id);
		}
		
		$centros = $this->Curso->Centro->find('list');
		$titulacions = $this->Curso->Titulacion->find('list');
		$ciclos = $this->Curso->Ciclo->find('list');
		$inscripcions = $this->Inscripcion->find('list');
		$this->set(compact('centros', 'titulacions', 'modalidads', 'ciclos', 'inscripcions', $inscripcions));
	}

	function delete($id = null) {
		if (!$id) {
			$this->Session->setFlash('Id no valido para sección', 'default', array('class' => 'alert alert-warning'));
			$this->redirect(array('action'=>'index'));
		}
		if ($this->Curso->delete($id)) {
			$this->Session->setFlash('La sección ha sido borrada', 'default', array('class' => 'alert alert-success'));
			$this->redirect(array('action'=>'index'));
		}
		$this->Session->setFlash('La sección no fue borrada. Intentelo nuevamente.', 'default', array('class' => 'alert alert-danger'));
		$this->redirect(array('action' => 'index'));
	}
}
?>