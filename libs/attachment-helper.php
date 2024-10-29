<?php defined( 'ABSPATH' ) || exit;


class GPLS_AEI_Attachment_Helper {

	/**
	 * Zip File Name.
	 *
	 * @var string
	 */
	private $zip_name = '';

	/**
	 * Zip File Object.
	 *
	 * @var object
	 */
	private $zip;

	/**
	 * Zip File Upload Directory Path.
	 *
	 * @var string
	 */
	public $upload_dir;

	/**
	 * Constructor.
	 *
	 */
	public function __construct( $zip_name = '' ) {
		$this->zip_name   = $zip_name;
		$this->upload_dir = wp_get_upload_dir();
	}

	/**
	 * Get Attachments Year/Month Folder
	 *
	 * @param int $attachment_id
	 * @return string
	 */
	public function get_attachment_upload_subfolder( $attachment_id ) {
		$attachment_url   = wp_get_attachment_url( $attachment_id );
		$upload_subfolder = _wp_get_attachment_relative_path( substr( $attachment_url, strlen( $this->upload_dir['baseurl'] . '/' ) ) );
		return $upload_subfolder;
	}

	/**
	 * Create Zip file for the Uploads attachments fils and folders from Attachments IDs.
	 *
	 * @param int $attachments_ids
	 * @return void
	 */
	public function prepare_attachments_zip_file( $attachments_ids ) {
		$zip_file = $this->create_zip();

		if ( ! $zip_file ) {
			return false;
		}

		foreach ( $attachments_ids as $attachment_id ) {
			$zip_file->add_attachment( $attachment_id );
		}

		$this->close_zip();
		return $this->upload_dir['path'] . '/' . $this->zip_name;
	}

	/**
	 * Add Attachments to the Zip file including their subfolder.
	 *
	 * @param int $attachment_id
	 * @return void
	 */
	public function add_attachment( $attachment_id ) {
		$media_url = wp_get_attachment_url( $attachment_id );

		if ( false === $media_url ) {
			return;
		}

		$media_path = str_replace( $this->upload_dir['baseurl'], $this->upload_dir['basedir'], $media_url );
		$media_name = substr( $media_url, strpos( $media_url, '/wp-content/uploads/' ) + 20 );

		if ( ! file_exists( $media_path ) ) {
			return;
		}

		$this->add_file( $media_path, $media_name );
	}

	/**
	 * Initialize the Zip File
	 *
	 * @param string $name
	 * @return object
	 */
	public function create_zip() {
		$file_full_path = $this->upload_dir['path'] . '/' . $this->zip_name;
		$this->zip      = new ZipArchive();
		if ( file_exists( $file_full_path ) ) {
			unlink( $file_full_path );
		}

		add_filter( 'upload_mimes', array( $this, 'allow_zip_type' ), 100, 1 );

		$res = $this->zip->open( $file_full_path , ZipArchive::OVERWRITE | ZipArchive::CREATE );

		if ( true === $res ) {
			return $this;
		} else {
			return false;
		}
	}

	/**
	 * Add File to the opened Zip File
	 *
	 * @param string $file_path
	 * @param string $file_name
	 * @return void
	 */
	public function add_file( $file_path, $file_name ) {
		if ( is_null( $this->zip ) ) {
			return  WP_Error(
				'zip_add_file',
				__( 'Zip file not created yet!', 'wordpress-importer' )
			);
		}
		$this->zip->addFile( $file_path, $file_name );
	}

	/**
	 * Add File to the opened Zip File
	 *
	 * @param array $files_paths_arr
	 * @return void
	 */
	public function add_files( $files_paths_arr ) {
		if ( is_null( $this->zip ) ) {
			return  WP_Error(
				'zip_add_file',
				__( 'Zip file not created yet!', 'wordpress-importer' )
			);
		}
		foreach ( $files_paths_arr as $file_path ) {
			$this->add_file( $file_path );
		}
	}

	/**
	 * Close the Zip File.
	 *
	 * @return void
	 */
	public function close_zip() {
		$this->zip->close();
		remove_filter( 'upload_mimes', array( $this, 'allow_zip_type' ), 100 );
	}


	/**
	 * Allow Zip File in uploads for the attachment export zip file.
	 *
	 * @param array $mime_types
	 * @return void
	 */
	public function allow_zip_type( $mime_types ) {
		$mime_types['zip'] = 'application/zip';
		return $mime_types;
	}

	/**
	 * Extract Attachments ZIP File.
	 *
	 * @param string $file_path
	 * @param int $file_id
	 * @return boolean
	 */
	public function extract_attachments_zip_file( $file_path, $file_id ) {
		$this->zip = new ZipArchive();

		$res = $this->zip->open( $file_path , ZipArchive::OVERWRITE | ZipArchive::CREATE );

		if ( true !== $res ) {
			return false;
		}

		$this->zip->extractTo( $this->upload_dir['basedir'] );
		$this->zip->close();

		wp_delete_attachment( $file_id, true );
		return true;
	}

}