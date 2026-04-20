window.PasskeyHelper = {
  arrayBufferToBase64(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i += 1) {
      binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
  },

  base64ToArrayBuffer(base64) {
    const prefix = '=?BINARY?B?';
    const suffix = '?=';
    let value = String(base64 || '');

    if (value.startsWith(prefix) && value.endsWith(suffix)) {
      value = value.substring(prefix.length, value.length - suffix.length);
    }

    const binary = atob(value);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i += 1) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
  },

  prepareOptions(object) {
    if (object === null || object === undefined) {
      return object;
    }

    if (Array.isArray(object)) {
      return object.map((item) => this.prepareOptions(item));
    }

    if (typeof object === 'object') {
      for (const key of Object.keys(object)) {
        const value = object[key];
        if (typeof value === 'string' && value.startsWith('=?BINARY?B?') && value.endsWith('?=')) {
          object[key] = this.base64ToArrayBuffer(value);
        } else if (typeof value === 'object') {
          object[key] = this.prepareOptions(value);
        }
      }
    }

    return object;
  },

  serializeRegistration(credential) {
    return {
      transport: credential.response.getTransports ? JSON.stringify(credential.response.getTransports()) : '[]',
      clientDataJSON: credential.response.clientDataJSON ? this.arrayBufferToBase64(credential.response.clientDataJSON) : null,
      attestationObject: credential.response.attestationObject ? this.arrayBufferToBase64(credential.response.attestationObject) : null,
    };
  },

  serializeAuthentication(credential) {
    return {
      id: credential.rawId ? this.arrayBufferToBase64(credential.rawId) : null,
      clientDataJSON: credential.response.clientDataJSON ? this.arrayBufferToBase64(credential.response.clientDataJSON) : null,
      authenticatorData: credential.response.authenticatorData ? this.arrayBufferToBase64(credential.response.authenticatorData) : null,
      signature: credential.response.signature ? this.arrayBufferToBase64(credential.response.signature) : null,
      userHandle: credential.response.userHandle ? this.arrayBufferToBase64(credential.response.userHandle) : null,
    };
  },
};
