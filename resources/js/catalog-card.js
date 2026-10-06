export default (url) => ({
    busy: false,
    message: '',
    showLink: false,
    async download() {
        if (this.busy) return;
        this.busy = true;
        this.message = 'Preparando tu catálogo actualizado…';
        try {
            const response = await fetch(url, { headers: { Accept: 'application/pdf' } });
            if (!response.ok || !response.headers.get('content-type')?.includes('application/pdf')) throw new Error();
            const blob = await response.blob();
            const objectUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = objectUrl;
            link.download = response.headers.get('content-disposition')?.match(/filename="([^"]+)"/)?.[1] || 'catalogo-neva-medic.pdf';
            document.body.appendChild(link); link.click(); link.remove();
            setTimeout(() => URL.revokeObjectURL(objectUrl), 60000);
            this.message = 'Catálogo listo. Revisa las descargas de tu navegador.';
        } catch {
            this.message = 'No se pudo generar el catálogo. Intenta de nuevo en un momento.';
        } finally { this.busy = false; }
    },
    async copy() {
        try { await navigator.clipboard.writeText(url); this.message = 'Enlace copiado. Puedes compartirlo donde quieras.'; }
        catch { this.showLink = true; this.message = 'Selecciona y copia el enlace que aparece abajo.'; }
    },
});
