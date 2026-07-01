/**
 * Offset handling that does not adjust body content.
 *
 * Similar to Drupal core displace, ExoDisplace does not move the body content
 * around and should be used when needing to find overlay offsets.
 */
class ExoDisplace {
  public offsets:ExoDisplaceOffsetsInterface = {
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
  };
  private readonly events = {
    calculate: new ExoEvent<ExoDisplaceOffsetsInterface>(),
    changed: new ExoEvent<ExoDisplaceOffsetsInterface>()
  };

  public calculate() {
    const offsets = this.calculateOffsets();
    const changed = offsets !== this.offsets;
    this.offsets = offsets;
    this.event('calculate').trigger(this.offsets);
    if (changed) {
      this.broadcast();
    }
    return this.offsets;
  }

  public broadcast() {
    this.event('changed').trigger(this.offsets);
  }

  protected calculateOffsets():ExoDisplaceOffsetsInterface {
    return {
      top: this.calculateOffset('top'),
      right: this.calculateOffset('right'),
      bottom: this.calculateOffset('bottom'),
      left: this.calculateOffset('left'),
    };
  }

  protected calculateOffset(edge:string):number {
    let edgeOffset = 0;
    const displacingElements = document.querySelectorAll(`[data-exo-edge='${edge}']`);
    const n = displacingElements.length;
    for (let i = 0; i < n; i++) {
      const el = displacingElements[i];
      // If the element is not visible, do not consider its dimensions. Use
      // getClientRects() (empty for any hide — inline, class, media query, or
      // ancestor) rather than inline el.style.display, which misses stylesheet
      // hides and leaves a hidden edge measuring 0 → a bogus viewport-sized
      // offset. Fixed edge bars still report rects when shown.
      if (el.getClientRects().length === 0 || getComputedStyle(el).visibility === 'hidden') {
        continue;
      }
      // If the offset data attribute contains a displacing value, use it.
      let displacement = parseInt(el.getAttribute(`data-exo-edge='${edge}'`), 10);
      // If the element's offset data attribute exits
      // but is not a valid number then get the displacement
      // dimensions directly from the element.
      if (isNaN(displacement)) {
        displacement = this.getRawOffset(el, edge);
      }
      // If the displacement value is larger than the current value for this
      // edge, use the displacement value.
      edgeOffset = Math.max(edgeOffset, displacement);
    }

    return edgeOffset;
  }

  /**
   * Calculates displacement for element based on its dimensions and placement.
   *
   * @param {HTMLElement} el
   *   The jQuery element whose dimensions and placement will be measured.
   *
   * @param {string} edge
   *   The name of the edge of the viewport that the element is associated
   *   with.
   *
   * @return {number}
   *   The viewport displacement distance for the requested edge.
   */
  protected getRawOffset(el, edge) {
    const $el = $(el);
    const documentElement = document.documentElement;
    let displacement = 0;
    const horizontal = (edge === 'left' || edge === 'right');
    // Get the offset of the element itself.
    let placement = $el.offset()[horizontal ? 'left' : 'top'];
    // Subtract scroll distance from placement to get the distance
    // to the edge of the viewport.
    placement -= window[`scroll${horizontal ? 'X' : 'Y'}`] || document.documentElement[`scroll${horizontal ? 'Left' : 'Top'}`] || 0;
    // Find the displacement value according to the edge.
    switch (edge) {
      // Left and top elements displace as a sum of their own offset value
      // plus their size.
      case 'top':
        // Total displacement is the sum of the elements placement and size.
        displacement = placement + $el.outerHeight();
        break;

      case 'left':
        // Total displacement is the sum of the elements placement and size.
        displacement = placement + $el.outerWidth();
        break;

      // Right and bottom elements displace according to their left and
      // top offset. Their size isn't important.
      case 'bottom':
        displacement = documentElement.clientHeight - placement;
        break;

      case 'right':
        displacement = documentElement.clientWidth - placement;
        break;

      default:
        displacement = 0;
    }
    return displacement;
  }

  /**
   * Get events for subscribing and triggering.
   */
  public event(type:string):ExoEvent<any> {
    if (typeof this.events[type] !== 'undefined') {
      return this.events[type].expose();
    }
    return null;
  }

}

Drupal.ExoDisplace = new ExoDisplace();
