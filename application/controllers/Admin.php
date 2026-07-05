<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		if (!$this->session->userdata('user_id')) {
			redirect('auth/login');
			exit;
		}
		if ($this->session->userdata('role') !== 'admin') {
			redirect('dashboard');
			exit;
		}
		$this->load->model('Module_model');
	}

	public function dashboard()
	{
		$data['title'] = 'Admin Dashboard';
		$data['total_users'] = $this->db->count_all('auth');
		$data['total_mentors'] = $this->db->where('role', 'instructor')->count_all_results('auth');
		$data['total_modules'] = $this->db->count_all('modules');
		$data['total_progress'] = $this->db->count_all('progress');
		$data['total_transactions'] = $this->db->count_all('transactions');
		$data['total_premium'] = $this->db->where('membership', 'premium')->count_all_results('auth');
		$data['recent_users'] = $this->db->order_by('created_at', 'DESC')->get('auth', 5)->result_array();
		$data['recent_transactions'] = $this->db
			->select('transactions.*, auth.nama, auth.email')
			->join('auth', 'auth.id = transactions.user_id', 'left')
			->order_by('transactions.created_at', 'DESC')
			->get('transactions', 5)
			->result_array();
		$data['nama'] = $this->session->userdata('nama');
		$this->load->view('admin/dashboard', $data);
	}


	public function mentors()
	{
		$data['title'] = 'Kelola Mentor';
		$data['mentors'] = $this->db->where('role', 'instructor')->order_by('created_at', 'DESC')->get('auth')->result_array();
		$data['nama'] = $this->session->userdata('nama');
		$this->load->view('admin/mentors', $data);
	}

	public function search_mentors()
	{
		$q = trim($this->input->get('q'));
		if (empty($q)) {
			redirect('admin/mentors');
			return;
		}

		$this->db->where('role', 'instructor');
		$this->db->group_start();
		$this->db->like('nama', $q);
		$this->db->or_like('email', $q);
		$this->db->group_end();
		$this->db->order_by('created_at', 'DESC');
		$data['mentors'] = $this->db->get('auth')->result_array();
		$data['search_query'] = $q;
		$data['nama'] = $this->session->userdata('nama');
		$data['title'] = 'Cari Mentor';
		$this->load->view('admin/mentors', $data);
	}

	public function create_mentor()
	{
		$data['mentors'] = $this->db->where('role', 'instructor')->get('auth')->result_array();
		$this->load->view('admin/create_mentor', $data);
	}

	public function store_mentor()
	{
		$nama = trim($this->input->post('nama'));
		$email = trim($this->input->post('email'));
		$password = $this->input->post('password');

		if (empty($nama) || empty($email) || empty($password)) {
			$this->session->set_flashdata('error', 'Semua field harus diisi.');
			redirect('admin/mentors');
			return;
		}

		$exists = $this->db->where('email', $email)->count_all_results('auth');
		if ($exists > 0) {
			$this->session->set_flashdata('error', 'Email sudah terdaftar.');
			redirect('admin/mentors');
			return;
		}

		$this->db->insert('auth', [
			'nama'       => $nama,
			'email'      => $email,
			'password'   => password_hash($password, PASSWORD_DEFAULT),
			'role'       => 'instructor',
			'membership' => 'free',
			'created_at' => date('Y-m-d H:i:s')
		]);

		$this->session->set_flashdata('success', 'Akun mentor "' . $nama . '" berhasil dibuat!');
		redirect('admin/mentors');
	}

	public function edit_mentor($id = 0)
	{
		$mentor = $this->db->where('id', $id)->where('role', 'instructor')->get('auth')->row_array();
		if (!$mentor) show_404();

		$data['mentor'] = $mentor;
		$data['nama'] = $this->session->userdata('nama');
		$data['title'] = 'Edit Mentor';
		$this->load->view('admin/edit_mentor', $data);
	}

	public function update_mentor($id = 0)
	{
		$mentor = $this->db->where('id', $id)->where('role', 'instructor')->get('auth')->row_array();
		if (!$mentor) show_404();

		$nama = trim($this->input->post('nama'));
		$email = trim($this->input->post('email'));
		$password = $this->input->post('password');

		if (empty($nama) || empty($email)) {
			$this->session->set_flashdata('error', 'Nama dan email harus diisi.');
			redirect('admin/edit_mentor/' . $id);
			return;
		}

		if ($email !== $mentor['email']) {
			$exists = $this->db->where('email', $email)->where('id !=', $id)->count_all_results('auth');
			if ($exists > 0) {
				$this->session->set_flashdata('error', 'Email sudah digunakan akun lain.');
				redirect('admin/edit_mentor/' . $id);
				return;
			}
		}

		$update = [
			'nama'  => $nama,
			'email' => $email,
		];

		if (!empty($password)) {
			$update['password'] = password_hash($password, PASSWORD_DEFAULT);
		}

		$this->db->where('id', $id)->update('auth', $update);

		$this->session->set_flashdata('success', 'Data mentor berhasil diperbarui!');
		redirect('admin/mentors');
	}

	public function delete_mentor($id = 0)
	{
		$mentor = $this->db->where('id', $id)->where('role', 'instructor')->get('auth')->row_array();
		if (!$mentor) show_404();

		$this->db->where('id', $id)->delete('auth');
		$this->session->set_flashdata('success', 'Akun mentor "' . $mentor['nama'] . '" berhasil dihapus.');
		redirect('admin/mentors');
	}


	public function members()
	{
		$data['title'] = 'Data Member';
		$data['members'] = $this->db->where('role', 'user')->order_by('created_at', 'DESC')->get('auth')->result_array();
		$data['nama'] = $this->session->userdata('nama');
		$this->load->view('admin/members', $data);
	}

	public function member_detail($id = 0)
	{
		$user = $this->db->where('id', $id)->where('role', 'user')->get('auth')->row_array();
		if (!$user) show_404();

		$modules = $this->Module_model->get_all();
		$progress_rows = $this->db->where('user_id', $id)->get('progress')->result_array();

		$slug_lang_map = [
			'javascript' => 'js', 'php' => 'php', 'python' => 'py', 'java' => 'java',
			'cpp' => 'cpp', 'c' => 'c', 'csharp' => 'cs', 'go' => 'go',
			'ruby' => 'ruby', 'rust' => 'rust', 'typescript' => 'ts', 'sqlite' => 'sql'
		];

		$progress_map = [];
		foreach ($progress_rows as $p) {
			$progress_map[$p['language']] = json_decode($p['completed_topics'], true) ?? [];
		}

		$course_progress = [];
		$completed_count = 0;
		$in_progress_count = 0;
		$total_done_topics = 0;

		foreach ($modules as $m) {
			$lang = $slug_lang_map[$m['slug']] ?? $m['slug'];
			$topics = $progress_map[$lang] ?? [];
			$done = count($topics);

			preg_match_all('/data-topic="' . $lang . '-(\d+)"/', $m['content'], $matches);
			$total = count(array_unique($matches[1] ?? []));
			if ($total == 0) $total = 12;

			$total_done_topics += $done;

			if ($done > 0) {
				$course_progress[] = [
					'module'   => $m,
					'done'     => $done,
					'total'    => $total,
					'pct'      => round(($done / $total) * 100),
					'status'   => $done >= $total ? 'completed' : 'in-progress'
				];
				if ($done >= $total) $completed_count++;
				else $in_progress_count++;
			} else {
				$course_progress[] = [
					'module'   => $m,
					'done'     => 0,
					'total'    => $total,
					'pct'      => 0,
					'status'   => 'not-started'
				];
			}
		}

		$cert_count = $this->db->where('user_id', $id)->count_all_results('user_certificates');
		$learning_hours = round(($total_done_topics * 0.5) * 2) / 2;

		$data['user'] = $user;
		$data['course_progress'] = $course_progress;
		$data['completed_count'] = $completed_count;
		$data['in_progress_count'] = $in_progress_count;
		$data['cert_count'] = $cert_count;
		$data['learning_hours'] = $learning_hours;
		$data['nama'] = $this->session->userdata('nama');
		$data['title'] = 'Detail Member: ' . $user['nama'];
		$this->load->view('admin/member_detail', $data);
	}

	public function delete_member($id = 0)
	{
		$user = $this->db->where('id', $id)->where('role', 'user')->get('auth')->row_array();
		if (!$user) show_404();

		$this->db->where('user_id', $id)->delete('progress');
		$this->db->where('user_id', $id)->delete('user_certificates');
		$this->db->where('user_id', $id)->delete('transactions');
		$this->db->where('id', $id)->delete('auth');

		$this->session->set_flashdata('success', 'Akun "' . $user['nama'] . '" beserta seluruh datanya berhasil dihapus.');
		redirect('admin/members');
	}

	public function search_members()
	{
		$q = trim($this->input->get('q'));
		if (empty($q)) {
			redirect('admin/members');
			return;
		}

		$this->db->where('role', 'user');
		$this->db->group_start();
		$this->db->like('nama', $q);
		$this->db->or_like('email', $q);
		$this->db->group_end();
		$this->db->order_by('created_at', 'DESC');
		$data['members'] = $this->db->get('auth')->result_array();
		$data['search_query'] = $q;
		$data['title'] = 'Cari Member';
		$data['nama'] = $this->session->userdata('nama');
		$this->load->view('admin/members', $data);
	}
	public function transactions()
	{
		$data['title'] = 'Transaksi';
		$data['transactions'] = $this->db
			->select('transactions.*, auth.nama, auth.email')
			->join('auth', 'auth.id = transactions.user_id')
			->order_by('transactions.created_at', 'DESC')
			->get('transactions')
			->result_array();
		$data['nama'] = $this->session->userdata('nama');
		$this->load->view('admin/transactions', $data);
	}

	public function search_transactions()
	{
		$q = trim($this->input->get('q'));
		if (empty($q)) {
			redirect('admin/transactions');
			return;
		}

		$this->db->select('transactions.*, auth.nama, auth.email');
		$this->db->join('auth', 'auth.id = transactions.user_id');
		$this->db->group_start();
		$this->db->like('auth.nama', $q);
		$this->db->or_like('auth.email', $q);
		$this->db->or_like('transactions.payment_method', $q);
		$this->db->or_like('transactions.status', $q);
		$this->db->group_end();
		$this->db->order_by('transactions.created_at', 'DESC');
		$data['transactions'] = $this->db->get('transactions')->result_array();
		$data['search_query'] = $q;
		$data['nama'] = $this->session->userdata('nama');
		$data['title'] = 'Cari Transaksi';
		$this->load->view('admin/transactions', $data);
	}

	public function grant_premium($id = 0)
	{
		$user = $this->db->where('id', $id)->get('auth')->row_array();
		if (!$user) show_404();

		$new_membership = ($user['membership'] === 'premium') ? 'free' : 'premium';
		$this->db->where('id', $id)->update('auth', ['membership' => $new_membership]);

		$msg = 'Membership "' . $user['nama'] . '" berhasil diubah menjadi ' . ucfirst($new_membership) . '!';
		$this->session->set_flashdata('success', $msg);
		redirect('admin/members');
	}
}
