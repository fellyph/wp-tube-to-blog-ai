/**
 * Browser audio recording helpers.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const PREFERRED_MIME_TYPES = [
	'audio/webm;codecs=opus',
	'audio/webm',
	'audio/ogg;codecs=opus',
	'audio/ogg',
	'audio/mp4',
	'audio/wav',
];

/**
 * Check whether the browser can record microphone audio.
 *
 * @return {boolean} Whether recording APIs are available.
 */
export function isAudioRecordingSupported() {
	return !! (
		window.navigator?.mediaDevices?.getUserMedia && window.MediaRecorder
	);
}

/**
 * Pick the best MIME type supported by MediaRecorder.
 *
 * @return {string} MIME type, or an empty string for browser default.
 */
export function getPreferredAudioMimeType() {
	if ( ! window.MediaRecorder?.isTypeSupported ) {
		return '';
	}

	return (
		PREFERRED_MIME_TYPES.find( ( mimeType ) =>
			window.MediaRecorder.isTypeSupported( mimeType )
		) || ''
	);
}

/**
 * Resolve a file extension from a MIME type.
 *
 * @param {string} mimeType MIME type.
 * @return {string} File extension.
 */
export function getAudioExtension( mimeType = '' ) {
	if ( mimeType.includes( 'ogg' ) ) {
		return 'ogg';
	}

	if ( mimeType.includes( 'mp4' ) || mimeType.includes( 'm4a' ) ) {
		return 'm4a';
	}

	if ( mimeType.includes( 'wav' ) ) {
		return 'wav';
	}

	if ( mimeType.includes( 'mpeg' ) || mimeType.includes( 'mp3' ) ) {
		return 'mp3';
	}

	return 'webm';
}

/**
 * Format bytes for UI labels.
 *
 * @param {number} bytes Byte count.
 * @return {string} Formatted bytes.
 */
export function formatBytes( bytes ) {
	if ( ! bytes ) {
		return '';
	}

	const units = [ 'B', 'KB', 'MB', 'GB' ];
	let size = bytes;
	let unitIndex = 0;

	while ( size >= 1024 && unitIndex < units.length - 1 ) {
		size /= 1024;
		unitIndex += 1;
	}

	return `${ size.toFixed( unitIndex === 0 ? 0 : 1 ) } ${
		units[ unitIndex ]
	}`;
}

/**
 * Format seconds as mm:ss.
 *
 * @param {number} seconds Duration in seconds.
 * @return {string} Formatted duration.
 */
export function formatDuration( seconds ) {
	const safeSeconds = Math.max( 0, Math.floor( seconds ) );
	const minutes = Math.floor( safeSeconds / 60 );
	const remainingSeconds = String( safeSeconds % 60 ).padStart( 2, '0' );

	return `${ minutes }:${ remainingSeconds }`;
}

/**
 * Create a File object from a recorded Blob.
 *
 * @param {Blob}   blob   Audio blob.
 * @param {string} prefix File name prefix.
 * @return {File|Blob} Uploadable file-like object.
 */
export function createAudioFileFromBlob(
	blob,
	prefix = 'wttba-audio-recording'
) {
	const extension = getAudioExtension( blob.type );
	const filename = `${ prefix }-${ new Date()
		.toISOString()
		.replace( /[:.]/g, '-' ) }.${ extension }`;

	if ( window.File ) {
		return new window.File( [ blob ], filename, {
			type: blob.type || `audio/${ extension }`,
		} );
	}

	return blob;
}

/**
 * Stop every track in a MediaStream.
 *
 * @param {MediaStream|null} stream Media stream.
 */
function stopStream( stream ) {
	stream?.getTracks().forEach( ( track ) => track.stop() );
}

/**
 * Hook for microphone recording.
 *
 * @param {Object}   options            Hook options.
 * @param {Function} options.onRecorded Callback fired with the recorded blob.
 * @return {Object} Recorder state and actions.
 */
export function useAudioRecorder( { onRecorded } = {} ) {
	const [ status, setStatus ] = useState( 'idle' );
	const [ error, setError ] = useState( '' );
	const [ recordedBlob, setRecordedBlob ] = useState( null );
	const [ recordedUrl, setRecordedUrl ] = useState( '' );
	const [ duration, setDuration ] = useState( 0 );
	const chunksRef = useRef( [] );
	const mediaRecorderRef = useRef( null );
	const streamRef = useRef( null );
	const timerRef = useRef( null );
	const startedAtRef = useRef( 0 );
	const urlRef = useRef( '' );
	const onRecordedRef = useRef( onRecorded );

	useEffect( () => {
		onRecordedRef.current = onRecorded;
	}, [ onRecorded ] );

	const clearTimer = () => {
		if ( timerRef.current ) {
			window.clearInterval( timerRef.current );
			timerRef.current = null;
		}
	};

	const revokeRecordedUrl = () => {
		if ( urlRef.current ) {
			window.URL.revokeObjectURL( urlRef.current );
			urlRef.current = '';
		}
	};

	const reset = () => {
		clearTimer();
		stopStream( streamRef.current );
		streamRef.current = null;
		mediaRecorderRef.current = null;
		chunksRef.current = [];
		revokeRecordedUrl();
		setStatus( 'idle' );
		setError( '' );
		setRecordedBlob( null );
		setRecordedUrl( '' );
		setDuration( 0 );
	};

	const start = async () => {
		if ( ! isAudioRecordingSupported() ) {
			setStatus( 'error' );
			setError(
				__(
					'Audio recording is not available in this browser.',
					'creatorstack-ai'
				)
			);
			return;
		}

		reset();
		setStatus( 'requesting' );

		try {
			const stream = await window.navigator.mediaDevices.getUserMedia( {
				audio: true,
			} );
			const mimeType = getPreferredAudioMimeType();
			const recorder = new window.MediaRecorder(
				stream,
				mimeType ? { mimeType } : undefined
			);

			streamRef.current = stream;
			mediaRecorderRef.current = recorder;
			chunksRef.current = [];

			recorder.addEventListener( 'dataavailable', ( event ) => {
				if ( event.data?.size ) {
					chunksRef.current.push( event.data );
				}
			} );

			recorder.addEventListener( 'stop', () => {
				clearTimer();
				stopStream( streamRef.current );
				streamRef.current = null;

				const blob = new window.Blob( chunksRef.current, {
					type: recorder.mimeType || mimeType || 'audio/webm',
				} );

				revokeRecordedUrl();

				if ( ! blob.size ) {
					setStatus( 'error' );
					setError(
						__(
							'No audio was captured. Try recording again.',
							'creatorstack-ai'
						)
					);
					return;
				}

				const url = window.URL.createObjectURL( blob );
				urlRef.current = url;
				setRecordedBlob( blob );
				setRecordedUrl( url );
				setDuration(
					Math.max(
						1,
						Math.round(
							( Date.now() - startedAtRef.current ) / 1000
						)
					)
				);
				setStatus( 'ready' );
				onRecordedRef.current?.( blob );
			} );

			startedAtRef.current = Date.now();
			recorder.start();
			setDuration( 0 );
			setStatus( 'recording' );
			timerRef.current = window.setInterval( () => {
				setDuration(
					Math.floor( ( Date.now() - startedAtRef.current ) / 1000 )
				);
			}, 500 );
		} catch ( err ) {
			stopStream( streamRef.current );
			streamRef.current = null;
			setStatus( 'error' );
			setError(
				err?.name === 'NotAllowedError'
					? __(
							'Microphone access was blocked. Allow microphone access and try again.',
							'creatorstack-ai'
					  )
					: __(
							'The microphone could not be started.',
							'creatorstack-ai'
					  )
			);
		}
	};

	const stop = () => {
		const recorder = mediaRecorderRef.current;

		if ( recorder && 'inactive' !== recorder.state ) {
			recorder.stop();
		}
	};

	useEffect( () => {
		return () => {
			clearTimer();
			stopStream( streamRef.current );
			revokeRecordedUrl();
		};
	}, [] );

	return {
		status,
		error,
		recordedBlob,
		recordedUrl,
		duration,
		isSupported: isAudioRecordingSupported(),
		isRecording: 'recording' === status || 'requesting' === status,
		hasRecording: !! recordedBlob,
		start,
		stop,
		reset,
	};
}
