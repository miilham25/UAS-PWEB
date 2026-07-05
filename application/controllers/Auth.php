<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends CI_Controller {

	public function login()
	{
		if ($this->session->userdata('user_id')) {
			$role = $this->session->userdata('role');
			if ($role === 'admin') {
				redirect('admin/dashboard'); 
			} elseif ($role === 'instructor') {
				redirect('mentor/dashboard');
			} else {
				redirect('dashboard');
			}
		}

		if ($this->input->method() === 'post') {
			$email    = $this->input->post('email');
			$password = $this->input->post('password');

			$user = $this->Auth_model->login($email, $password);

			if ($user) {
				$this->session->set_userdata([
					'user_id'    => $user['id'],
					'nama'       => $user['nama'],
					'email'      => $user['email'],
					'role'       => $user['role'],
					'membership' => $user['membership'],
					'logged_in'  => TRUE
				]);

				if ($user['role'] === 'admin') {
					redirect('admin/dashboard');
				} elseif ($user['role'] === 'instructor') {
					redirect('mentor/dashboard');
				} else {
					redirect('dashboard');
				}
			} else {
				$data['error'] = 'Email atau password salah.';
				$this->load->view('auth/login', $data);
				return;
			}
		}

		$this->load->view('auth/login');
	}

	public function register()
	{
		if ($this->session->userdata('user_id')) {
			$role = $this->session->userdata('role');
			if ($role === 'admin') {
				redirect('admin/dashboard');
			} elseif ($role === 'instructor') {
				redirect('mentor/dashboard');
			} else {
				redirect('dashboard');
			}
		}

		if ($this->input->method() === 'post') {
			$nama     = $this->input->post('first_name') . ' ' . $this->input->post('last_name');
			$email    = $this->input->post('email');
			$password = $this->input->post('password');

			if (empty($nama) || empty($email) || empty($password)) {
				$data['reg_error'] = 'Semua field harus diisi.';
				$this->load->view('auth/login', $data);
				return;
			}

			$user_id = $this->Auth_model->register([
				'nama'     => trim($nama),
				'email'    => trim($email),
				'password' => $password
			]);

			if ($user_id) {
				$this->session->set_flashdata('reg_success', 'Registrasi berhasil! Silakan login.');
				redirect('auth/login');
			} else {
				$data['reg_error'] = 'Email sudah terdaftar.';
				$this->load->view('auth/login', $data);
			}
			return;
		}

		$this->load->view('auth/login');
	}

	public function logout()
	{
		$this->session->sess_destroy();
		redirect('landingpage');
	}
}
