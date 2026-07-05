<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends CI_Controller {

	private $slug_lang_map = [
		'javascript' => 'js',
		'php'        => 'php',
		'python'     => 'py',
		'java'       => 'java',
		'cpp'        => 'cpp',
		'c'          => 'c',
		'csharp'     => 'cs',
		'go'         => 'go',
		'ruby'       => 'ruby',
		'rust'       => 'rust',
		'typescript' => 'ts',
		'sqlite'     => 'sql',
	];

	private function lang_code($slug)
	{
		return $this->slug_lang_map[$slug] ?? $slug;
	}

	public function index()
	{
		if (!$this->session->userdata('user_id')) {
			redirect('auth/login');
			return;
		}

		$user_id = $this->session->userdata('user_id');
		$nama = $this->session->userdata('nama');
		$modules = $this->Module_model->get_all();
		$progress_rows = $this->db->get_where('progress', ['user_id' => $user_id])->result_array();

		$progress_map = [];
		$total_done_topics = 0;
		$total_all_topics = 0;
		$completed_modules = 0;
		$in_progress_module = null;
		$in_progress_done = 0;
		$in_progress_total = 0;

		foreach ($progress_rows as $p) {
			$progress_map[$p['language']] = json_decode($p['completed_topics'], true) ?? [];
		}

		foreach ($modules as $m) {
			$slug = $m['slug'];
			$lang = $this->lang_code($slug);
			$topics = $progress_map[$lang] ?? [];
			$done = count($topics);

			preg_match_all('/data-topic="' . $lang . '-(\d+)"/', $m['content'], $matches);
			$total = count(array_unique($matches[1] ?? []));
			if ($total == 0) $total = 12;

			$total_done_topics += $done;
			$total_all_topics += $total;

			if ($done > 0 && $done < $total) {
				if (!$in_progress_module) {
					$in_progress_module = $m;
					$in_progress_done = $done;
					$in_progress_total = $total;
				}
			}

			if ($done >= $total) {
				$completed_modules++;
			}
		}

		$learning_hours = round(($total_done_topics * 0.5) * 2) / 2;

		$streak = 0;
		if (!empty($progress_rows)) {
			$dates = [];
			foreach ($progress_rows as $p) {
				if ($p['updated_at']) {
					$dates[] = date('Y-m-d', strtotime($p['updated_at']));
				} elseif ($p['created_at']) {
					$dates[] = date('Y-m-d', strtotime($p['created_at']));
				}
			}
			$dates = array_unique($dates);
			rsort($dates);

			$streak = 0;
			$check = date('Y-m-d');
			foreach ($dates as $d) {
				if ($d == $check) {
					$streak++;
					$check = date('Y-m-d', strtotime($check . ' -1 day'));
				}
			}
		}

		$levels = ['beginner', 'intermediate', 'advanced'];
		$recommended = [];
		foreach ($levels as $level) {
			if (count($recommended) >= 3) break;
			foreach ($modules as $m) {
				if (count($recommended) >= 3) break;
				if ($m['difficulty'] !== $level) continue;
				$slug = $m['slug'];
				$lang = $this->lang_code($slug);
				$topics = $progress_map[$lang] ?? [];
				if (count($topics) == 0) {
					$recommended[] = $m;
				}
			}
		}
		if (count($recommended) < 3) {
			foreach ($modules as $m) {
				if (count($recommended) >= 3) break;
				$slug = $m['slug'];
				$lang = $this->lang_code($slug);
				$topics = $progress_map[$lang] ?? [];
				preg_match_all('/data-topic="' . $lang . '-(\d+)"/', $m['content'], $matches);
				$total = count(array_unique($matches[1] ?? []));
				if ($total == 0) $total = 12;
				$done = count($topics);
				if ($done > 0 && $done < $total) {
					$already = false;
					foreach ($recommended as $r) { if ($r['slug'] === $slug) { $already = true; break; } }
					if (!$already) $recommended[] = $m;
				}
			}
			if (count($recommended) < 3) {
				foreach ($modules as $m) {
					if (count($recommended) >= 3) break;
					$slug = $m['slug'];
					$lang = $this->lang_code($slug);
					$topics = $progress_map[$lang] ?? [];
					preg_match_all('/data-topic="' . $lang . '-(\d+)"/', $m['content'], $matches);
					$total = count(array_unique($matches[1] ?? []));
					if ($total == 0) $total = 12;
					$done = count($topics);
					if ($done >= $total) {
						$already = false;
						foreach ($recommended as $r) { if ($r['slug'] === $slug) { $already = true; break; } }
						if (!$already) $recommended[] = $m;
					}
				}
			}
		}

		$data['nama'] = $nama;
		$data['completed_modules'] = $completed_modules;
		$data['learning_hours'] = $learning_hours;
		$data['streak'] = $streak;
		$data['in_progress_module'] = $in_progress_module;
		$data['in_progress_done'] = $in_progress_done;
		$data['in_progress_total'] = $in_progress_total;
		$data['membership'] = $this->session->userdata('membership');
		$data['recommended'] = $recommended;

		$this->load->view('dashboard', $data);
	}
}
