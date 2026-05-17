import { motion } from 'framer-motion';
import { ArrowRight } from 'lucide-react';
import { ShinyText } from './ShinyText';
import { Navigation } from './Navigation';

export const HeroSection = () => {
  return (
    <div className="relative w-full h-screen overflow-hidden bg-black">
      {/* Video Background */}
      <video
        autoPlay
        loop
        muted
        playsInline
        className="absolute inset-0 w-full h-full object-cover"
      >
        <source src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260328_105406_16f4600d-7a92-4292-b96e-b19156c7830a.mp4" type="video/mp4" />
      </video>

      {/* Dark Overlay */}
      <div className="absolute inset-0 bg-black/50" />

      {/* Content */}
      <div className="relative z-10 w-full h-full flex flex-col">
        {/* Navigation */}
        <Navigation />

        {/* Main Content */}
        <div className="flex-1 flex flex-col justify-center items-center px-4 sm:px-6 lg:px-8 pt-8 sm:pt-12 lg:pt-20 pb-6 sm:pb-8 lg:pb-12">
          {/* Top Section - Two Column Layout */}
          <div className="w-full max-w-6xl mb-8 sm:mb-10 lg:mb-16">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8 lg:gap-20">
              {/* Left Column */}
              <motion.p
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.8, delay: 0.2 }}
                className="text-white/80 text-sm sm:text-base lg:text-lg leading-relaxed order-2 lg:order-1"
              >
                We deliver transformative programs that empower emerging product designers with cutting-edge expertise and vision to thrive globally.
              </motion.p>

              {/* Right Column */}
              <motion.p
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.8, delay: 0.3 }}
                className="text-white/80 text-sm sm:text-base lg:text-lg text-center lg:text-right order-1 lg:order-2 mb-4 sm:mb-0"
              >
                <span className="block text-3xl sm:text-4xl lg:text-5xl font-bold text-white mb-2">8000+</span>
                <span>Talented Designers Launched !</span>
              </motion.p>
            </div>
          </div>

          {/* Hero Section - Center */}
          <div className="w-full max-w-6xl text-center">
            {/* Small Uppercase Text */}
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ duration: 0.8, delay: 0.4 }}
              className="mb-6 sm:mb-8 lg:mb-10"
            >
              <p className="text-white/70 text-xs sm:text-sm lg:text-base uppercase tracking-wide lg:tracking-wider font-medium">
                Seats for Next Program Opening Soon
              </p>
            </motion.div>

            {/* Main Heading with Shiny Effect */}
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.8, delay: 0.5 }}
              className="mb-8 sm:mb-12 lg:mb-16"
            >
              <div style={{ lineHeight: '0.9' }}>
                <h1 className="text-3xl xs:text-4xl sm:text-5xl md:text-6xl lg:text-8xl xl:text-9xl font-bold text-white tracking-tighter">
                  Become
                </h1>
                <h1 className="text-3xl xs:text-4xl sm:text-5xl md:text-6xl lg:text-8xl xl:text-9xl font-bold tracking-tighter">
                  <ShinyText
                    text="Product Leader."
                    className="text-3xl xs:text-4xl sm:text-5xl md:text-6xl lg:text-8xl xl:text-9xl font-bold inline-block"
                  />
                </h1>
              </div>
            </motion.div>

            {/* CTA Button */}
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.8, delay: 0.6 }}
              className="flex justify-center"
            >
              <button className="group inline-flex items-center justify-center gap-2 sm:gap-3 px-6 sm:px-8 lg:px-10 py-3 sm:py-4 lg:py-5 bg-black hover:bg-gray-900 active:bg-gray-800 text-white text-sm sm:text-base lg:text-lg rounded-full transition-all duration-200 min-h-[50px] font-medium">
                <span>Apply for Next Enrollment</span>
                <ArrowRight className="w-4 h-4 sm:w-5 sm:h-5 flex-shrink-0 group-hover:translate-x-1.5 group-active:translate-x-2 transition-transform duration-200" />
              </button>
            </motion.div>
          </div>
        </div>
      </div>
    </div>
  );
};
