/* Read-only guided tour. Application navigation is injected, never data writes. */
(() => {
  const STORAGE_KEY = 'cuaderno.demo-tour.v1';
  const STEPS = [
    ['Tu cuaderno de prueba', 'Esta demo tiene ejercicios y marcas de ejemplo. Puedes cambiarlo todo: solo se conserva durante esta sesión. Reiniciar demo recupera los ejemplos.'],
    ['Empieza por un ejercicio', 'Busca en el catálogo o crea un ejercicio con su nombre y unidad. El grupo muscular es opcional: puedes organizarlo después.'],
    ['Apunta tu próxima marca', 'Al seleccionar un ejercicio verás su mejor marca y el último registro. Introduce tu marca de hoy en kg, repeticiones, minutos o kilómetros y guárdala cuando pruebes por tu cuenta.'],
    ['Organízate a tu manera', 'Los entrenamientos son listas opcionales. “Todos los ejercicios” permite salir de esa lista: un ejercicio externo se registra sin entrenamiento y no se añade automáticamente.'],
    ['Mira cómo avanzas', 'En Histórico eliges un ejercicio y comparas sus marcas, la mejor y la más reciente. La gráfica muestra la mejor marca de cada día. Si has borrado los ejemplos, podrás ver aquí los registros que crees después.'],
    ['Tu catálogo, a tu gusto', 'En Gestión puedes editar ejercicios y elegir los que forman cada entrenamiento. También encontrarás gimnasios opcionales y la exportación e importación de copias de seguridad.'],
  ];

  class DemoTour {
    constructor({ isDemo, prepare, finish, report = () => {} }) {
      this.isDemo = isDemo; this.prepare = prepare; this.finish = finish; this.report = report;
      this.dialog = document.getElementById('demoTourDialog');
      this.panel = document.getElementById('demoTourPanel');
      this.spotlight = document.getElementById('demoTourSpotlight');
      this.active = false; this.busy = false; this.remembered = false; this.version = 0;
      this.onLayout = () => {
        if (this.frame) cancelAnimationFrame(this.frame);
        this.frame = requestAnimationFrame(() => this.position());
      };
      this.onResize = () => { this.position(); this.reveal(); this.onLayout(); };
      document.getElementById('demoTourBack').addEventListener('click', () => this.back());
      document.getElementById('demoTourNext').addEventListener('click', () => this.next());
      document.getElementById('demoTourSkip').addEventListener('click', () => this.close(true));
      this.dialog.addEventListener('cancel', event => { event.preventDefault(); this.close(true); });
    }

    hasSeen() {
      if (this.remembered) return true;
      try { if (localStorage.getItem(STORAGE_KEY)) return true; } catch { /* try session storage */ }
      try { return Boolean(sessionStorage.getItem(STORAGE_KEY)); } catch { return false; }
    }

    async start(manual = false) {
      if (!this.isDemo() || this.active || (!manual && this.hasSeen())) return;
      this.returnFocus = document.activeElement;
      this.active = true;
      this.index = 0;
      document.body?.classList.add('demo-tour-active');
      this.dialog.showModal();
      window.addEventListener('resize', this.onResize);
      window.addEventListener('scroll', this.onLayout, true);
      await this.show(0);
    }

    async show(index) {
      const version = ++this.version;
      this.busy = true;
      this.target = null;
      this.spotlight.hidden = true;
      this.dialog.setAttribute('aria-busy', 'true');
      document.getElementById('demoTourBack').disabled = true;
      document.getElementById('demoTourNext').disabled = true;
      document.getElementById('demoTourTitle').textContent = STEPS[index][0];
      document.getElementById('demoTourText').textContent = STEPS[index][1];
      document.getElementById('demoTourProgress').textContent = `Paso ${index + 1} de ${STEPS.length}`;
      this.position();
      try {
        const target = await this.prepare(index, () => !this.active || version !== this.version);
        if (!this.active || version !== this.version) return;
        this.target = target;
        this.index = index;
        this.reveal();
        this.position();
      } catch (error) {
        if (!this.active || version !== this.version) return;
        this.stop();
        this.report(error);
        return;
      }
      this.busy = false;
      this.dialog.setAttribute('aria-busy', 'false');
      document.getElementById('demoTourBack').disabled = index === 0;
      const next = document.getElementById('demoTourNext');
      next.disabled = false;
      next.textContent = index === STEPS.length - 1 ? 'Empezar a probar' : 'Siguiente';
      next.focus({ preventScroll: true });
    }

    position() {
      if (!this.active) return;
      const width = window.innerWidth, height = window.innerHeight;
      const mobile = width < 700;
      const panelWidth = Math.min(360, width - 24);
      this.panel.style.width = `${panelWidth}px`;
      const panelHeight = this.panel.getBoundingClientRect().height;
      const rect = this.target?.getClientRects().length ? this.target.getBoundingClientRect() : null;
      let left = Math.max(12, width - panelWidth - 24);
      let top = Math.max(12, height - panelHeight - 24);
      if (rect && !mobile) {
        if (rect.right + panelWidth + 32 <= width) { left = rect.right + 16; top = rect.top; }
        else if (rect.left >= panelWidth + 32) { left = rect.left - panelWidth - 16; top = rect.top; }
        else if (rect.bottom + panelHeight + 32 <= height) top = rect.bottom + 16;
        else if (rect.top >= panelHeight + 32) top = rect.top - panelHeight - 16;
      }
      this.panel.style.left = `${Math.max(12, Math.min(left, width - panelWidth - 12))}px`;
      this.panel.style.top = `${Math.max(12, Math.min(top, height - panelHeight - 12))}px`;
      this.spotlight.hidden = !rect;
      if (rect) {
        const x = Math.max(6, rect.left - 6), y = Math.max(mobile ? 70 : 6, rect.top - 6);
        const bottom = Math.min(rect.bottom + 6, mobile ? top - 12 : height - 6);
        this.spotlight.style.left = `${x}px`;
        this.spotlight.style.top = `${y}px`;
        this.spotlight.style.width = `${Math.max(0, Math.min(rect.right + 6, width - 6) - x)}px`;
        this.spotlight.style.height = `${Math.max(0, bottom - y)}px`;
        this.spotlight.hidden = bottom <= y;
      }
      this.dialog.classList.toggle('has-target', Boolean(rect) && !this.spotlight.hidden);
    }

    reveal() {
      if (!this.active || !this.target?.getClientRects().length) return;
      const rect = this.target.getBoundingClientRect();
      const mobile = window.innerWidth < 700;
      const top = mobile ? 82 : 24;
      const available = window.innerHeight - top - (mobile ? this.panel.getBoundingClientRect().height + 36 : 48);
      if (rect.top < top || rect.bottom > top + available) {
        // Instant scroll also respects reduced motion and avoids moving targets between steps.
        window.scrollBy?.({ top: rect.top - top, behavior: 'instant' });
      }
    }

    async next() {
      if (!this.active || this.busy) return;
      if (this.index === STEPS.length - 1) await this.close(true, true);
      else await this.show(this.index + 1);
    }

    async back() {
      if (this.active && !this.busy && this.index > 0) await this.show(this.index - 1);
    }

    stop() {
      this.active = false; this.busy = false; this.version++;
      this.target = null; this.spotlight.hidden = true;
      document.body?.classList.remove('demo-tour-active');
      if (this.dialog.open) this.dialog.close();
      window.removeEventListener('resize', this.onResize);
      window.removeEventListener('scroll', this.onLayout, true);
      if (this.frame) cancelAnimationFrame(this.frame);
    }

    async close(remember = false, completed = false) {
      if (!this.active) return;
      if (remember) {
        this.remembered = true;
        const value = completed ? 'completed' : 'dismissed';
        try { localStorage.setItem(STORAGE_KEY, value); }
        catch { try { sessionStorage.setItem(STORAGE_KEY, value); } catch { /* in-memory fallback */ } }
      }
      this.stop();
      try { if (this.isDemo()) await this.finish(); } catch (error) { this.report(error); }
      const target = this.returnFocus !== document.body && this.returnFocus?.isConnected && this.returnFocus.getClientRects().length
        ? this.returnFocus : document.getElementById('trainingExerciseSearch');
      target?.focus({ preventScroll: true });
    }
  }
  window.DemoTour = DemoTour;
})();
