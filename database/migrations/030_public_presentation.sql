ALTER TABLE portfolios
ADD COLUMN public_presentation TEXT NOT NULL DEFAULT 'landing'
CHECK (public_presentation IN (
    'landing','cards','pages','editorial','timeline','mosaic','cinema','dashboard',
    'story','studio','minimal','atlas','glass','orbit','museum','journal','steps',
    'split','stack','polaroid','blueprint','paper','neon','nature','aurora','bento',
    'classroom','storyboard','spotlight'
));
