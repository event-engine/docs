<?php
/**
 * tobiju
 *
 * @link      https://github.com/tobiju/bookdown-bootswatch-templates for the canonical source repository
 * @copyright Copyright (c) 2015 Tobias Jüschke
 * @license   https://github.com/tobiju/bookdown-bootswatch-templates/blob/master/LICENSE.txt New BSD License
 */

if (!$this->page->hasNestedTocEntries()) {
    return;
}

?>

<h1><?= $this->page->getNumberAndTitle(); ?></h1>

<?= $this->tocListHelper($this->page->getNestedTocEntries()); ?>
