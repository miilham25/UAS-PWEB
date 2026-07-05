<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Chat extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		if (!$this->session->userdata('user_id')) {
			redirect('auth/login');
			exit;
		}
		if ($this->session->userdata('membership') !== 'premium') {
			redirect('dashboard');
			exit;
		}
	}

	public function index()
	{
		$data['title'] = 'Live Chat Mentor';
		$data['nama'] = $this->session->userdata('nama');
		$this->load->view('chat/index', $data);
	}

	public function api_conversations()
	{
		$user_id = $this->session->userdata('user_id');

		$mentors = $this->db->query("
			SELECT DISTINCT a.id, a.nama, a.email,
				(SELECT message FROM chat_messages
				 WHERE (sender_id = a.id AND receiver_id = $user_id)
				    OR (sender_id = $user_id AND receiver_id = a.id)
				 ORDER BY created_at DESC LIMIT 1
				) as last_message,
				(SELECT created_at FROM chat_messages
				 WHERE (sender_id = a.id AND receiver_id = $user_id)
				    OR (sender_id = $user_id AND receiver_id = a.id)
				 ORDER BY created_at DESC LIMIT 1
				) as last_time,
				(SELECT COUNT(*) FROM chat_messages
				 WHERE sender_id = a.id AND receiver_id = $user_id AND is_read = 0
				) as unread
			FROM auth a
			WHERE a.role = 'instructor'
			AND a.id IN (
				SELECT DISTINCT CASE WHEN sender_id = $user_id THEN receiver_id ELSE sender_id END
				FROM chat_messages
				WHERE sender_id = $user_id OR receiver_id = $user_id
			)
			ORDER BY last_time DESC
		")->result_array();

		if (empty($mentors)) {
			$mentors = $this->db->where('role', 'instructor')->get('auth')->result_array();
			foreach ($mentors as &$m) {
				$m['last_message'] = null;
				$m['last_time'] = null;
				$m['unread'] = '0';
			}
		}

		echo json_encode($mentors);
	}

	public function api_messages($mentor_id = 0)
	{
		$user_id = $this->session->userdata('user_id');

		$mentor = $this->db->where('id', $mentor_id)->where('role', 'instructor')->get('auth')->row_array();
		if (!$mentor) {
			echo json_encode(['error' => 'Mentor tidak ditemukan']);
			return;
		}

		$messages = $this->db->query("
			SELECT * FROM chat_messages
			WHERE (sender_id = $user_id AND receiver_id = $mentor_id AND deleted_by_sender = 0)
			   OR (sender_id = $mentor_id AND receiver_id = $user_id AND deleted_by_receiver = 0)
			ORDER BY created_at ASC
		")->result_array();

		$this->db->where('sender_id', $mentor_id)
			->where('receiver_id', $user_id)
			->where('is_read', 0)
			->update('chat_messages', ['is_read' => 1]);

		echo json_encode(['messages' => $messages, 'mentor' => $mentor]);
	}

	public function api_send()
	{
		$user_id = $this->session->userdata('user_id');
		$receiver_id = $this->input->post('receiver_id');
		$message = trim($this->input->post('message'));

		if (empty($receiver_id) || empty($message)) {
			echo json_encode(['error' => 'Data tidak lengkap']);
			return;
		}

		$receiver = $this->db->where('id', $receiver_id)->where('role', 'instructor')->get('auth')->row_array();
		if (!$receiver) {
			echo json_encode(['error' => 'Penerima tidak ditemukan']);
			return;
		}

		$this->db->insert('chat_messages', [
			'sender_id'   => $user_id,
			'sender_role' => 'user',
			'receiver_id' => $receiver_id,
			'message'     => $message,
			'is_read'     => 0,
		]);

		echo json_encode(['success' => true, 'id' => $this->db->insert_id()]);
	}

	public function api_delete($msg_id = 0)
	{
		$user_id = $this->session->userdata('user_id');

		$msg = $this->db->where('id', $msg_id)->get('chat_messages')->row_array();
		if (!$msg) {
			echo json_encode(['error' => 'Pesan tidak ditemukan']);
			return;
		}

		if ($msg['sender_id'] == $user_id) {
			$this->db->where('id', $msg_id)->update('chat_messages', ['deleted_by_sender' => 1]);
		} elseif ($msg['receiver_id'] == $user_id) {
			$this->db->where('id', $msg_id)->update('chat_messages', ['deleted_by_receiver' => 1]);
		} else {
			echo json_encode(['error' => 'Tidak berhak menghapus']);
			return;
		}

		echo json_encode(['success' => true]);
	}

	public function api_unread()
	{
		$user_id = $this->session->userdata('user_id');

		$count = $this->db->where('receiver_id', $user_id)
			->where('is_read', 0)
			->count_all_results('chat_messages');

		echo json_encode(['unread' => $count]);
	}
}
