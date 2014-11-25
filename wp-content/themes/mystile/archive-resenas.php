<?php
// File Security Check
if ( ! empty( $_SERVER['SCRIPT_FILENAME'] ) && basename( __FILE__ ) == basename( $_SERVER['SCRIPT_FILENAME'] ) ) {
    die ( 'You do not have sufficient permissions to access this page!' );
}
?>
<?php get_header(); ?>
<div class="novedad-bg">
    <div class="container">
        <div class="row">
            <div class="col-md-6 ">
                <div id="take" class="carousel-
                caption jumbotron fleft">
                    <h1>Reseñas</h1>
                    <p>You don't get sick, I do. That's also clear. But for some reason, you and I react the exact same way to water.</p>
                </div>

            </div>
        </div>
    </div>
</div>   
    <div id="content" class="col-full">
    	
    	<?php woo_main_before(); ?>
    	
		<section id="main" class="col-left"> 

		<?php if (have_posts()) : $count = 0; ?>
        
           

        <?php

        $settings = array(
                    'thumb_w' => 787, 
                    'thumb_h' => 300, 
                    'thumb_align' => 'alignleft'
                    );
                    
            $settings = woo_get_dynamic_values( $settings );
        	// Display the description for this archive, if it's available.
        	woo_archive_description();
        ?>
        
	        <div class="fix"></div>
        
            <?php woo_loop_before(); ?>
            <!-- the loop -->
            <?php if ( have_posts() ) : while (have_posts()) : the_post(); ?>

				<article <?php post_class('post'); ?>>

                    <div class="reviewer">       
                        <img src="<?php bloginfo('template_directory') ?>/images/avatar-male.jpg">        
                        <div class="sign"></div>      
                    </div>

                    <div class="reviewer">
                        <?php 

                            $image = get_field('imagen');
                            $size = 'thumbnail'; // (thumbnail, medium, large, full or custom size)
                            if( $image ) {

                                echo wp_get_attachment_image( $image, $size );

                            }

                        ?>
                    </div>
                    
                    <section class="post-content">
                    
                        <header class="resign-data">
                            <h1><a href="<?php the_permalink(); ?>" rel="bookmark" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a></h1>
                            <p class="author mbottom10">Por: <span class="stronged"><?php the_author(); ?></span></p>
                        </header>
                
                        <section class="entry rev-excerpt">
                        <?php if ( isset( $woo_options['woo_post_content'] ) && $woo_options['woo_post_content'] == 'novedades' ) { the_content( __( 'Continue Reading &rarr;', 'woothemes' ) ); } else { the_excerpt(); } ?>
                        <a class="fleft" href="<?php the_permalink(); ?>" rel="bookmark" title="<?php the_title_attribute(); ?>">Continúa Leyendo</a>
                        <?php woo_post_meta(); ?>
                        </section>
                
                          
                    </section><!--/.post-content -->
            
                </article><!-- /.post -->

			<?php endwhile; ?>

            <?php else: ?>
            
            <?php endif; ?>  
            
	        <?php else: ?>
	        
	            <article <?php post_class(); ?>>
	                <p><?php _e( 'Sorry, no posts matched your criteria.', 'woothemes' ); ?></p>
	            </article><!-- /.post -->
	        
	        <?php endif; ?>  
	        
	        <?php woo_loop_after(); ?>
    
			<?php woo_pagenav(); ?>
                
		</section><!-- /#main -->
		
		<?php woo_main_after(); ?>

        <?php get_sidebar('content'); ?>

    </div><!-- /#content -->
		
<?php get_footer(); ?>