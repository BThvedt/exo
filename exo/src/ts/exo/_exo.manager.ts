class ExoManager<T> {
  protected label:string = 'ExoManager';
  protected doDebug:boolean = false;
  protected settingsGroup:string = '';
  protected instanceSettingsGroup:string = '';
  protected instanceClass:ExoSettingInstance;
  protected instances:ExoCollection<T>;
  protected ready:boolean = false;
  protected time:number;

  constructor() {
    this.build();
  }

  /**
   * Initial build.
   */
  public build():void {
    this.instances = new ExoCollection<T>();
  }

  /**
   * Drupal Attach event.
   * @param context
   */
  public attach(context:HTMLElement):Promise<boolean> {
    this.ready = false;
    return new Promise((resolve, reject) => {
      this.debug('log', 'Attach: Start', this.settingsGroup, this.instanceSettingsGroup);
      this.buildInstances().then(status => {
        this.ready = true;
        resolve(status);
        this.debug('info', 'Attach: Finish', status);
      });
    });
  }

  /**
   * Build instances.
   */
  protected buildInstances(): Promise<boolean> {
    return new Promise((resolve, reject) => {
      const promises:Array<Promise<boolean>> = [];
      const settings = this.getSettingsGroup(this.instanceSettingsGroup);
      // If empty, we can resolve right away as there is nothing to do.
      // jQuery 4 removed isEmptyObject; check own-key count instead.
      if (
        settings === null ||
        (Array.isArray(settings) && !settings.length) ||
        (typeof settings === 'object' && Object.keys(settings).length === 0)
      ) {
        this.debug('log', 'Build Instances: Empty');
        resolve(true);
        return;
      }
      this.debug('log', 'Build Instances: Start', settings);
      this.time = Date.now();
      for (let id in settings) {
        if (settings.hasOwnProperty(id)) {
          promises.push(this.buildInstance(id, settings[id]));
        }
      }
      Promise.all(promises).then(values => {
        this.debug('info', 'Build Instances: Finish', values);
        this.time = Date.now();
        resolve(true);
      }, reject => {
        resolve(false);
      });
    });
  }

  /**
   * Build instance.
   */
  protected buildInstance(id:string, data:any):Promise<boolean> {
    let instance = this.instances.getById(id);
    if (!instance) {
      // We want to immediately create AND store our instance so that concurrent
      // requests will use this instance instead of creating a new instance.
      instance = this.createInstance(id, data);
      this.addInstance(id, instance);
    }
    return new Promise((resolve, reject) => {
      data._exoTime = this.time;
      this.debug('log', 'Build Instance: Start', '[' + id + ']', data);
      instance.build(data).then(data => {
        this.debug('info', 'Build Instance: Finish', '[' + id + ']', status);
        if (data !== null) {
          if (this.instanceSettingsGroup !== '') {
            this.purgeSetting(this.instanceSettingsGroup, id);
          }
          // Allow instances to act after build.
          instance.afterBuild();
        }
        resolve(true);
      }, reject);
    });
  }

  /**
   * Manually create an instance.
   */
  public create(id:string, data:any):Promise<boolean> {
    return new Promise((resolve, reject) => {
      this.buildInstance(id, data).then(success => {
        resolve(success);
      });
    });
  }

  /**
   * Will resolve when attach has completed.
   */
  public isReady():Promise<ExoCollection<T>> {
    return new Promise((resolve, reject) => {
      const updateTimer = () => {
        if (this.ready === true) {
          this.debug('info', 'Is Ready');
          resolve(this.getInstances());
        }
        else {
          setTimeout(updateTimer, 20);
        }
      }
      updateTimer();
    });
  }

  /**
   * Create instance.
   */
  protected createInstance(id:string, data:any) {
    return new this.instanceClass(id);
  }

  /**
   * Add instance.
   */
  protected addInstance(id:string, instance:T) {
    this.instances.add(id, instance);
  }

  /**
   * Remove instance.
   */
  public removeInstance(id:string) {
    this.instances.remove(id);
  }

  /**
   * Get all instances.
   */
  public getInstances():ExoCollection<T> {
    return this.instances;
  }

  /**
   * Get a specific instance by id.
   *
   * @param id
   *   The instance id.
   */
  public getInstance(id:string):T {
    return this.getInstances().getById(id);
  }

  /**
   * Get a settings group.
   *
   * @param groupId
   *   The group id.
   */
  public getSettingsGroup(groupId:string):ExoSettingsGroupInterface {
    if (drupalSettings[this.settingsGroup] && drupalSettings[this.settingsGroup][groupId]) {
      return drupalSettings[this.settingsGroup][groupId];
    }
    return null;
  }

  /**
   * Remove a settings group from drupalSettings.
   *
   * @param groupId
   *   The group id.
   */
  public purgeSettingsGroup(groupId:string):this {
    if (drupalSettings[this.settingsGroup] && drupalSettings[this.settingsGroup][groupId]) {
      this.debug('warn', 'Purge Settings Group', this.settingsGroup, groupId);
      delete drupalSettings[this.settingsGroup][groupId];
    }
    return this;
  }

  /**
   * Get an element's settings.
   *
   * @param settingsId
   *   The element id.
   * @param groupId
   *   The group id.
   */
  public getSetting(groupId:string, settingsId:string):any {
    const group = this.getSettingsGroup(groupId);
    if (group && group[settingsId]) {
      return group[settingsId];
    }
    return null;
  }

  /**
   * Get an element's settings from drupalSettings.
   *
   * @param settingsId
   *   The element id.
   * @param groupId
   *   The group id.
   */
  public purgeSetting(groupId:string, settingsId:string):this {
    const group = this.getSettingsGroup(groupId);
    if (group && group[settingsId]) {
      this.debug('warn', 'Purge Settings', groupId, settingsId, group[settingsId]);
      delete group[settingsId];
    }
    return this;
  }

  /**
   * Log debug message.
   */
  public debug(type:string, ...args) {
    if (this.doDebug === true) {
      Drupal.Exo.debug(type, this.label, ...args);
    }
  }

}
