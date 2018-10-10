<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

class Akt extends CI_Controller {
	function __construct(){
		parent::__construct();
		$this->load->library(array('PHPExcel','PHPExcel/IOFactory'));
		if($this->session->userdata('masuk')==TRUE){			
			$this->url = $this->session->userdata('url');	
			$this->client = new nusoap_client($this->url,true);
			
			$this->proxy = $this->client->getProxy();	
			$this->token = $this->session->userdata('gettoken');	
		}
		else{
			redirect('gettoken_jyn/logout');	
		}
	}

	function listmhs(){
			$table = 'mahasiswa';
			$datacari = $this->input->post('cari',true);
			if(empty($datacari)){
				$filter= '';
			}
				else{
				$filter = "nipd ilike '%".$datacari."%' OR nm_pd ilike '%".$datacari."%'";
			}
			//$limit = 2;
			//$filter = "nm_pd ilike '%novi%'";
			$order = 'nipd desc';
			//$offset = 0;
			$totaldata = $this->proxy->GetCountRecordset($this->token,$table,$filter);
			
			
			$config['base_url'] = site_url('mahasiswa/listmhs');
			$config['total_rows'] = $totaldata['result'];
			$config['per_page'] = $per_page = 10;
			$config['uri_segment'] = 3;
			$config['uri_segment']=3;
			$config['first_link']='Awal';
			$config['last_link']='Akhir';
			$config['next_link']='Next  &rarr; ';
			$config['prev_link']='&larr; Prev';
			$this->pagination->initialize($config);
			$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
			
			$result = $this->proxy->GetListMahasiswa($this->token,$filter,$order,$per_page,$page);
			
			$data['paging'] = $this->pagination->create_links();
			
			$data['cari']=$datacari;
			$data['result'] = $result ;
			$data['total'] = $totaldata;
			
			
			$x['isi'] = $this->load->view('mahasiswa/listdata',$data,true);
			$this->load->view('template',$x);		
		
	}
	
	function import_updatedata(){
			$d['file_url']= base_url().'asset/dist/file/import_akt.xlsx';
			$x['isi'] = $this->load->view('akt/forminputupdate',$d,true);	
			$this->load->view('template',$x);
	}
	
	function import_insertdata(){
			$d['file_url']= base_url().'asset/dist/file/import_akt.xlsx';
			$x['isi'] = $this->load->view('akt/forminputinsert',$d,true);	
			$this->load->view('template',$x);
	}
	
	function insertdata(){
			$this->benchmark->mark('mulai');
			$tabel1 = 'aktivitas_mahasiswa';
			$tabel2 = 'anggota_aktivitas';
			
			$error_file = '';
			$sukses_count = 0;
			$error_count = 0;
			$update_count = 0;
			$sukses_msg = array();
			$error_msg = array();
			$update_msg = array();
			$fileName = $_FILES['import']['name'];
			 
			$config['upload_path'] = './asset/upload/'; //buat folder dengan nama assets di root folder
			$config['file_name'] = $fileName;
			$config['allowed_types'] = 'xls|xlsx|csv';
			$config['max_size'] = 10000;
			 
			$this->load->library('upload');
			$this->upload->initialize($config);
			 
			if(! $this->upload->do_upload('import') ){
				$error_file = $this->upload->display_errors();	
				$link = site_url('akt/import_insertdata');
				echo "<script>
					window.alert('$error_file'); location.href=('$link');
				</script>";		
			}
			
			else{     
			$media = $this->upload->data();
			$inputFileName = './asset/upload/'.$media['file_name'];
			 
			try {
					$inputFileType = IOFactory::identify($inputFileName);
					$objReader = IOFactory::createReader($inputFileType);
					$objPHPExcel = $objReader->load($inputFileName);
				} catch(Exception $e) {
					die('Error loading file "'.pathinfo($inputFileName,PATHINFO_BASENAME).'": '.$e->getMessage());
				}
	 
				$sheet = $objPHPExcel->getSheet(0);
				$highestRow = $sheet->getHighestRow();
				$highestColumn = $sheet->getHighestColumn();
				 
				for ($row = 2; $row <= $highestRow; $row++){  //  Read a row of data into an array                 
					$rowData = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row,
													NULL,
													TRUE,
													FALSE);
													 
					//Sesuaikan sama nama kolom tabel di database                                
					$sem 		= $rowData[0][1];
					$jenis_akt 	= $rowData[0][2];
					$judul 		= $rowData[0][3];
					$lokasi 	= $rowData[0][4];
					$no_sk 		= $rowData[0][5];
					$tgl_sk 	= $rowData[0][6];
					$ket_akt 	= $rowData[0][7];
					$kodeprodi 	= $rowData[0][8];
					$jenis_anggota = $rowData[0][9];
					$nim 		= $rowData[0][10];
					$nama_pd	= $rowData[0][11];
					$jns_peran_mhs	= $rowData[0][12];

					
					$idsp = $this->session->userdata('idsp');
					
					//Filter Prodi
					$filter_sms = "id_sp='".$this->session->userdata('idsp')."' and kode_prodi ilike '%".$kodeprodi."%'";
							$temp_sms = $this->proxy->GetRecord($this->token,'sms',$filter_sms);
							if($temp_sms['result']){
								$id_sms = $temp_sms['result']['id_sms'];	
					}
					else $id_sms ='';					
					
					$record['id_smt'] = $sem;
					$record['judul_akt_mhs'] = $judul;					
					$record['lokasi_kegiatan'] = $lokasi;
					$record['sk_tugas'] = $no_sk;
					$record['tgl_sk_tugas'] = $tgl_sk;
					$record['ket_akt'] = $ket_akt;
					$record['a_komunal'] = $jenis_anggota;
					$record['id_jns_akt_mhs'] = $jenis_akt;
					$record['id_sms'] = $id_sms;
					$data = $record;
					
					$insert_data= $this->proxy->InsertRecord($this->token,$tabel1,json_encode($data));
					
					if($insert_data['result']){
						if($insert_data['result']['error_desc']==NULL){
							
							$id_pd = $insert_data['result']['id_akt_mhs'];
							$filter_sms = "id_sp='".$this->session->userdata('idsp')."' and kode_prodi ilike '%".$kodeprodi."%'";
							$temp_sms = $this->proxy->GetRecord($this->token,'sms',$filter_sms);
							if($temp_sms['result']){
								$id_sms = $temp_sms['result']['id_sms'];	
							}

							//filter Mahasiswa PT
							$filter_mhspt = "nipd ilike '%".$nim."%'";
							$temp_mhspt = $this->proxy->GetRecord($this->token,'mahasiswa_pt',$filter_mhspt);
							if($temp_mhspt['result']){
								$id_reg_pd = $temp_mhspt['result']['id_reg_pd'];	
								$id_pd = $temp_mhspt['result']['id_pd'];	
							}else $id_reg_pd='';

							//Filter Mahasiswa 
							$filtermhs = "id_pd='".$id_pd."'";
							$tempmhs = $this->proxy->GetRecord($this->token,'mahasiswa',$filtermhs);
							if($tempmhs['result']){
								$nm_pd = $tempmhs['result']['nm_pd'];	
							}else $id_reg_pd='';

							//$record['id_ang_akt_mhs'] = '1';
							$record_pt['id_reg_pd'] = $id_reg_pd;
							$record_pt['id_akt_mhs'] = $id_pd;					
							$record_pt['nm_pd'] = $nama_pd;
							$record_pt['nipd'] = $nim;
							$record_pt['jns_peran_mhs'] = $jns_peran_mhs;
							
							$data_pt = $record_pt;
							$insert_pt = $this->proxy->InsertRecord($this->token,$tabel2,json_encode($data_pt));
							//var_dump($insert_pt);
							if($insert_pt['result']['error_desc']==NULL){
								++$sukses_count;
								$sukses_msg[] = 'Data "'.$rowData[0][11].' / '.$judul.'" berhasil di tambahkan <br>';							
							}
							else{
								++$error_count;
								$error_msg[] = "Error pada data ( '".$jenis_akt." / ".$judul.") : '".$insert_data['result']['error_desc']."' <br>";	
							}
							
						}
						else{
						++$error_count;
						$error_msg[] = "Error pada data ".$nm_pd." - ".$insert_pt['result']['error_desc'];	
						}
					}
					
				}
			}
			
			$this->benchmark->mark('selesai');
			if($sukses_count!=0){
				$d['sukses_jml'] = $sukses_count." Data berhasil ditambahkan, detail :<br>";
				$d['sukses_msg'] = $sukses_msg;
			}
			if($error_count != 0){
				$d['error_jml'] = $error_count." Data gagal di tambahkan,detail : <br>" ;
				$d['error_msg'] = $error_msg;
			}
			unlink('./asset/upload/'.$fileName);					
			$d['eksekusi_waktu'] = $this->benchmark->elapsed_time('mulai', 'selesai')." Detik";
			$x['isi'] = $this->load->view('akt/pesaninsert',$d,true);	
			$this->load->view('template',$x); 	
		 
	}
}
?>
