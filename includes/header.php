<div class="header">
    <?php if(isset($headerParams['titulo'])) { ?>
        <div class="header-title active"><?php echo $headerParams['titulo'] ?></div>
    <?php } ?>

    <?php if(isset($headerParams['btn_atras'])) { ?>
        <div class="header-leftoption" onclick="<?php echo $headerParams['btn_atras']; ?>"><i class="fal fa-sign-out-alt fa-rotate-180"></i></div>
    <?php } ?>

    <?php if(isset($headerParams['buscador'])) { ?>
        <div class="buscador-navegador">
            <input type="text" class="textfield-buscador-navegador" placeholder="buscar...">
            <div class="lupa-buscador-navegador"><i class="far fa-search"></i></div>
        </div>
    <?php } ?>

    <?php if(isset($headerParams['btn_logout'])) { ?>
        <div class="header-rightoption btn_logout" onclick="location.href='<?php $ROOT; ?>/php/sesion/logout'"><i class="fa-light fa-arrow-right-from-bracket"></i></div>
    <?php } ?>
</div>